<?php

namespace App\Filament\Resources\PlayGroups;

use App\Enums\PlayGroupKind;
use App\Filament\Resources\PlayGroups\Pages\ManagePlayGroups;
use App\Models\PlayGroup;
use App\Platform\Audit\AuditLogger;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The central play-group configuration: who may sit at which tables and what they play for, for poker
 * ("chips": the minimum bet is the big blind) and for every other game ("stake": each player bets within
 * the range; winners share the pot). Changes apply to new tables; edits are audited.
 */
class PlayGroupResource extends Resource
{
    protected static ?string $model = PlayGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Games';

    protected static ?string $navigationLabel = 'Play groups';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('min_balance')->label('Minimum balance to enter')->helperText('Checked when a player sits down; never charged.')
                    ->numeric()->integer()->required()->minValue(0)->maxValue(100_000_000),
                TextInput::make('min_bet')->label(fn (?PlayGroup $record) => $record?->kind === PlayGroupKind::Chips ? 'Minimum bet (big blind)' : 'Minimum bet')
                    ->numeric()->integer()->required()->minValue(1)->maxValue(100_000_000),
                TextInput::make('max_bet')->label('Maximum bet')
                    ->numeric()->integer()->minValue(1)->maxValue(100_000_000)
                    ->gte('min_bet')
                    ->required(fn (?PlayGroup $record) => $record?->kind === PlayGroupKind::Stake)
                    ->visible(fn (?PlayGroup $record) => $record?->kind === PlayGroupKind::Stake),
                Toggle::make('is_enabled')->label('Open for play')->helperText('A closed group is hidden from players.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultGroup(Group::make('kind')->getTitleFromRecordUsing(fn (PlayGroup $record) => $record->kind === PlayGroupKind::Chips ? 'Poker (chips)' : 'All other games (stakes)'))
            ->defaultSort('position')
            ->columns([
                TextColumn::make('slug')->label('Group')->formatStateUsing(fn (string $state) => ucfirst($state)),
                TextColumn::make('min_balance')->label('Min balance')->numeric(),
                TextColumn::make('min_bet')->label('Min bet')->numeric(),
                TextColumn::make('max_bet')->label('Max bet')->numeric()->placeholder('—'),
                IconColumn::make('is_enabled')->label('Open')->boolean(),
            ])
            ->recordActions([
                EditAction::make()->after(fn (PlayGroup $record) => app(AuditLogger::class)->record(
                    'play_group.updated',
                    $record,
                    $record->only(['kind', 'slug', 'min_balance', 'min_bet', 'max_bet', 'is_enabled']),
                    auth('admin')->user(),
                )),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePlayGroups::route('/'),
        ];
    }
}
