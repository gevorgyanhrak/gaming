<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserStatus;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Platform\Moderation\ModerationException;
use App\Platform\Moderation\UserModeration;
use Closure;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Carbon;

/**
 * Account details plus moderation. All actions go through UserModeration (rules + audit log).
 *
 * @property User $record
 */
class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ban')
                ->color('danger')
                ->icon('heroicon-o-no-symbol')
                ->visible(fn () => $this->canModerate() && $this->record->status !== UserStatus::Banned)
                ->schema([
                    Textarea::make('reason')->required()->maxLength(500),
                    DateTimePicker::make('expires_at')->label('Ban until')->helperText('Leave empty for a permanent ban.')->after('now'),
                ])
                ->action(fn (array $data) => $this->moderate(
                    fn (UserModeration $m, User $actor) => $m->ban(
                        $this->record,
                        $actor,
                        $data['reason'],
                        filled($data['expires_at'] ?? null) ? Carbon::parse($data['expires_at']) : null,
                    ),
                    'User banned. They are signed out on their next request.',
                )),
            Action::make('unban')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => $this->canModerate() && $this->record->status === UserStatus::Banned)
                ->action(fn () => $this->moderate(fn (UserModeration $m, User $actor) => $m->unban($this->record, $actor), 'Ban lifted.')),
            Action::make('deactivate')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('The user is signed out and cannot sign in until the account is activated again.')
                ->visible(fn () => $this->canModerate() && $this->record->status === UserStatus::Active)
                ->action(fn () => $this->moderate(fn (UserModeration $m, User $actor) => $m->deactivate($this->record, $actor), 'Account deactivated.')),
            Action::make('activate')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => $this->canModerate() && $this->record->status === UserStatus::Deactivated)
                ->action(fn () => $this->moderate(fn (UserModeration $m, User $actor) => $m->activate($this->record, $actor), 'Account activated.')),
            Action::make('roles')
                ->label('Manage roles')
                ->icon('heroicon-o-shield-check')
                ->visible(fn () => auth('admin')->user()?->can('manageRoles', $this->record) ?? false)
                ->fillForm(fn () => ['roles' => $this->record->getRoleNames()->all()])
                ->schema([
                    CheckboxList::make('roles')
                        ->options(array_combine(array_keys(RoleSeeder::ROLES), array_keys(RoleSeeder::ROLES)))
                        ->helperText('No role means a regular player. Staff need an email address to sign in here.'),
                ])
                ->action(fn (array $data) => $this->moderate(
                    fn (UserModeration $m, User $actor) => $m->syncRoles($this->record, $actor, array_values($data['roles'] ?? [])),
                    'Roles updated.',
                )),
        ];
    }

    private function canModerate(): bool
    {
        return auth('admin')->user()?->can('moderate', $this->record) ?? false;
    }

    /**
     * @param  Closure(UserModeration, User): void  $action
     */
    private function moderate(Closure $action, string $success): void
    {
        /** @var User $actor */
        $actor = auth('admin')->user();

        try {
            $action(app(UserModeration::class), $actor);
        } catch (ModerationException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        $this->record->refresh();
        Notification::make()->success()->title($success)->send();
    }
}
