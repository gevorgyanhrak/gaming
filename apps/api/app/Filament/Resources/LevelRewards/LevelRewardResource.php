<?php

namespace App\Filament\Resources\LevelRewards;

use App\Filament\Resources\LevelRewards\Pages\ManageLevelRewards;
use App\Models\LevelReward;
use App\Platform\Levels\LevelItems;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * What each level gives when a player reaches it (ADR 0018): Fishkas, items (frames, badges, emoji
 * packs from the code catalog) and whether it is a milestone. Levels without a row give the default
 * from the settings. Changes apply to future level-ups; edits are audited (AdminChangeObserver).
 */
class LevelRewardResource extends Resource
{
    protected static ?string $model = LevelReward::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|UnitEnum|null $navigationGroup = 'Games';

    protected static ?string $navigationLabel = 'Level rewards';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('level')->label('Level')
                    ->numeric()->integer()->required()->minValue(2)->maxValue(500)
                    ->unique(ignoreRecord: true)
                    ->disabledOn('edit'),
                TextInput::make('fishkas')->label('Fishkas')
                    ->numeric()->integer()->required()->minValue(0)->maxValue(1_000_000),
                Select::make('items')->label('Items')->multiple()
                    ->options(LevelItems::options())
                    ->formatStateUsing(fn (?array $state) => array_map(fn (array $item) => "{$item['kind']}:{$item['key']}", $state ?? []))
                    ->dehydrateStateUsing(fn (?array $state) => array_values(array_map(function (string $value) {
                        [$kind, $key] = explode(':', $value, 2);

                        return ['kind' => $kind, 'key' => $key];
                    }, $state ?? [])))
                    ->helperText('A player owns an item from this level on. The highest frame and badge are shown.'),
                Toggle::make('is_milestone')->label('Milestone')->inline(false)
                    ->helperText('A bigger celebration in the level-up popup.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('level')
            ->columns([
                TextColumn::make('level')->sortable(),
                TextColumn::make('fishkas')->numeric(),
                TextColumn::make('items')->label('Items')
                    ->state(fn (LevelReward $record) => implode(', ', array_map(
                        fn (array $item) => LevelItems::options()["{$item['kind']}:{$item['key']}"] ?? "{$item['kind']}:{$item['key']}",
                        $record->items ?? [],
                    )))
                    ->placeholder('—'),
                IconColumn::make('is_milestone')->label('Milestone')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLevelRewards::route('/'),
        ];
    }
}
