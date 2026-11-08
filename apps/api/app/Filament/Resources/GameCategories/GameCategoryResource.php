<?php

namespace App\Filament\Resources\GameCategories;

use App\Filament\Resources\GameCategories\Pages\CreateGameCategory;
use App\Filament\Resources\GameCategories\Pages\EditGameCategory;
use App\Filament\Resources\GameCategories\Pages\ListGameCategories;
use App\Filament\Support\Translatable;
use App\Models\GameCategory;
use App\Platform\Audit\AuditLogger;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The shelves of the catalog. A deleted category's games stay, without a category. Changes are audited.
 */
class GameCategoryResource extends Resource
{
    protected static ?string $model = GameCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Games';

    protected static ?string $navigationLabel = 'Categories';

    protected static ?int $navigationSort = 15;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Translatable::input('name', 'Name', maxLength: 40),
            TextInput::make('slug')->required()->maxLength(40)->alphaDash()
                ->notIn(['_other'])
                ->unique(ignoreRecord: true)
                ->helperText('A short id in Latin letters, used in links. Avoid changing it later.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->afterReordering(fn (array $order) => app(AuditLogger::class)->record('game_category.reordered', null, ['order' => $order], auth('admin')->user()))
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('slug'),
                TextColumn::make('games_count')->counts('games')->label('Games'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->modalDescription('Its games stay in the catalog, without a category.'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGameCategories::route('/'),
            'create' => CreateGameCategory::route('/create'),
            'edit' => EditGameCategory::route('/{record}/edit'),
        ];
    }
}
