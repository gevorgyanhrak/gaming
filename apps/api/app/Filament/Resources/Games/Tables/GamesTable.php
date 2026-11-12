<?php

namespace App\Filament\Resources\Games\Tables;

use App\Models\Game;
use App\Models\User;
use App\Platform\Audit\AuditLogger;
use App\Platform\Games\GameAdministration;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

/**
 * Game Management (owner, 2026-11-12): every game with its status, and the controls — enable, disable,
 * maintenance with a message, end the games under way, delete (archive or purge). All through
 * GameAdministration, audited.
 */
class GamesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->afterReordering(fn (array $order) => app(AuditLogger::class)->record('game.reordered', null, ['order' => $order], auth('admin')->user()))
            ->columns([
                ImageColumn::make('icon_path')->label('')->disk('public')->square(),
                TextColumn::make('name')->description(fn ($record) => $record->slug),
                TextColumn::make('status')->label('Status')->badge()
                    ->state(fn (Game $record) => $record->status())
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->color(fn (string $state) => match ($state) {
                        'enabled' => 'success',
                        'maintenance' => 'warning',
                        'archived' => 'gray',
                        default => 'danger',
                    }),
                TextColumn::make('category.name')->label('Category')->placeholder('—'),
                IconColumn::make('is_visible')->label('Visible')->boolean(),
                IconColumn::make('bots_enabled')->label('AI')->boolean(),
                TextColumn::make('modes_count')->counts('modes')->label('Modes'),
                TextColumn::make('players')->state(fn ($record) => $record->min_players === $record->max_players
                    ? (string) $record->min_players
                    : "{$record->min_players}–{$record->max_players}"),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'enabled' => 'Enabled', 'disabled' => 'Disabled', 'maintenance' => 'Maintenance', 'archived' => 'Archived',
                ])->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                    'enabled' => $query->where('is_enabled', true)->where('maintenance', false)->whereNull('archived_at'),
                    'disabled' => $query->where('is_enabled', false)->whereNull('archived_at'),
                    'maintenance' => $query->where('maintenance', true)->whereNull('archived_at'),
                    'archived' => $query->whereNotNull('archived_at'),
                    default => $query,
                }),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    Action::make('enable')->label('Enable')->icon('heroicon-o-play')->color('success')
                        ->visible(fn (Game $record) => $record->status() !== 'enabled')
                        ->requiresConfirmation()
                        ->modalDescription('Players can find the game and start new games again.')
                        ->action(fn (Game $record) => self::run(fn (GameAdministration $admin, ?User $actor) => $admin->enable($record, $actor), 'Game enabled.')),
                    Action::make('disable')->label('Disable')->icon('heroicon-o-pause')->color('danger')
                        ->visible(fn (Game $record) => $record->is_enabled)
                        ->requiresConfirmation()
                        ->modalDescription('The game is hidden from the catalog and lobby, and no new games, rooms or matchmaking start. Games under way go on.')
                        ->action(fn (Game $record) => self::run(fn (GameAdministration $admin, ?User $actor) => $admin->disable($record, $actor), 'Game disabled.')),
                    Action::make('maintenance')->label('Maintenance mode')->icon('heroicon-o-wrench-screwdriver')->color('warning')
                        ->visible(fn (Game $record) => ! $record->maintenance && $record->archived_at === null)
                        ->modalDescription('Players see the game with this message and can\'t start new games. Games under way go on.')
                        ->schema([
                            Textarea::make('en')->label('Message (English)')->maxLength(300),
                            Textarea::make('ru')->label('Message (Russian)')->maxLength(300),
                            Textarea::make('hy')->label('Message (Armenian)')->maxLength(300),
                        ])
                        ->action(fn (Game $record, array $data) => self::run(fn (GameAdministration $admin, ?User $actor) => $admin->maintenance($record, true, $data, $actor), 'Game is in maintenance.')),
                    Action::make('end_maintenance')->label('End maintenance')->icon('heroicon-o-check-circle')->color('success')
                        ->visible(fn (Game $record) => $record->maintenance)
                        ->requiresConfirmation()
                        ->action(fn (Game $record) => self::run(fn (GameAdministration $admin, ?User $actor) => $admin->maintenance($record, false, null, $actor), 'Maintenance ended.')),
                    Action::make('end_games')->label('End games under way')->icon('heroicon-o-stop-circle')->color('danger')
                        ->visible(fn (Game $record) => app(GameAdministration::class)->activeRooms($record) > 0)
                        ->requiresConfirmation()
                        ->modalHeading(fn (Game $record) => 'End '.app(GameAdministration::class)->activeRooms($record).' game(s) now?')
                        ->modalDescription('Every game of this game under way or waiting for players ends at once: nobody wins, everyone gets their bet or buy-in back.')
                        ->action(fn (Game $record) => self::run(fn (GameAdministration $admin, ?User $actor) => $admin->endActiveGames($record, $actor), 'Games ended and bets returned.')),
                    Action::make('delete')->label('Delete')->icon('heroicon-o-trash')->color('danger')
                        ->modalHeading('Delete this game?')
                        ->modalDescription('Archive hides the game everywhere and keeps its games, results and statistics (you can enable it again). Delete permanently removes its modes, rooms, moves, statistics and AI profiles; it can\'t be undone and is refused while games are under way. Other games are not affected.')
                        ->schema([
                            Radio::make('mode')->label('What happens to its data')->required()->default('archive')
                                ->options(['archive' => 'Archive: hide the game, keep its history', 'purge' => 'Delete permanently with all its data']),
                            TextInput::make('confirm')->label(fn (Game $record) => "Type the game's code \"{$record->slug}\" to confirm")
                                ->required()->in(fn (Game $record) => [$record->slug]),
                        ])
                        ->action(fn (Game $record, array $data) => self::run(
                            fn (GameAdministration $admin, ?User $actor) => $data['mode'] === 'purge' ? $admin->purge($record, $actor) : $admin->archive($record, $actor),
                            $data['mode'] === 'purge' ? 'Game deleted.' : 'Game archived.',
                        )),
                ]),
            ]);
    }

    /**
     * @param  callable(GameAdministration, User|null): mixed  $step
     */
    private static function run(callable $step, string $done): void
    {
        try {
            $actor = auth('admin')->user();
            $step(app(GameAdministration::class), $actor instanceof User ? $actor : null);
            Notification::make()->title($done)->success()->send();
        } catch (RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }
}
