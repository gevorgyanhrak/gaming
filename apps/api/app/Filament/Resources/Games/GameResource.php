<?php

namespace App\Filament\Resources\Games;

use App\Filament\Resources\Games\Pages\EditGame;
use App\Filament\Resources\Games\Pages\ListGames;
use App\Filament\Resources\Games\RelationManagers\ModesRelationManager;
use App\Filament\Resources\Games\Schemas\GameForm;
use App\Filament\Resources\Games\Tables\GamesTable;
use App\Models\Game;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Catalog, availability and bot settings of the games. Games themselves are defined in code
 * (each has an engine), so they are seeded and can't be created or deleted here.
 */
class GameResource extends Resource
{
    protected static ?string $model = Game::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static string|UnitEnum|null $navigationGroup = 'Games';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'slug';

    public static function form(Schema $schema): Schema
    {
        return GameForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GamesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ModesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGames::route('/'),
            'edit' => EditGame::route('/{record}/edit'),
        ];
    }
}
