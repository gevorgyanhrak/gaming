<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserStatus;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Platform\Fishka\FishkaWallets;
use App\Platform\Moderation\ModerationException;
use App\Platform\Moderation\UserModeration;
use Closure;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Account details plus moderation. Moderation goes through UserModeration and Fishka changes through
 * FishkaWallets::adjust (both audited).
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
            ActionGroup::make([
                $this->fishkaAction('addFishkas', 'Add Fishkas', 'credit', 'heroicon-o-plus-circle', 'Fishkas to add'),
                $this->fishkaAction('deductFishkas', 'Deduct Fishkas', 'debit', 'heroicon-o-minus-circle', 'Fishkas to deduct'),
                $this->fishkaAction('setFishkas', 'Set exact balance', 'set', 'heroicon-o-pencil-square', 'New balance'),
            ])
                ->label('Fishkas')
                ->icon('heroicon-o-banknotes')
                ->button()
                ->color('warning')
                ->visible(fn () => $this->canModerate()),
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

    /**
     * @param  'credit'|'debit'|'set'  $mode
     */
    private function fishkaAction(string $name, string $label, string $mode, string $icon, string $amountLabel): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->modalDescription(fn () => 'Current balance: '.number_format(app(FishkaWallets::class)->wallet($this->record)->balance).' Fishkas.')
            ->schema([
                TextInput::make('amount')->label($amountLabel)->required()->numeric()->integer()->minValue($mode === 'set' ? 0 : 1)->maxValue(10_000_000),
                Textarea::make('reason')->required()->maxLength(500)->helperText('Saved with the change and shown in the transaction history.'),
            ])
            ->action(function (array $data) use ($mode) {
                /** @var User $actor */
                $actor = auth('admin')->user();
                try {
                    $entry = app(FishkaWallets::class)->adjust($this->record, $actor, $mode, (int) $data['amount'], $data['reason']);
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title(collect($e->errors())->flatten()->first() ?? 'Not changed.')->send();

                    return;
                }
                $this->record->refresh();
                Notification::make()->success()
                    ->title($entry === null ? 'Balance unchanged.' : 'Balance is now '.number_format($entry->balance_after).' Fishkas.')
                    ->send();
            });
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
