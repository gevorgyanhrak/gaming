<?php

namespace App\Filament\Resources\SlotGames;

use App\Filament\Resources\Games\Schemas\GameForm;
use App\Filament\Resources\SlotGames\Pages\CreateSlotGame;
use App\Filament\Resources\SlotGames\Pages\EditSlotGame;
use App\Filament\Resources\SlotGames\Pages\ListSlotGames;
use App\Filament\Support\Translatable;
use App\Models\SlotGame;
use App\Platform\Audit\AuditLogger;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * A provider's slot games as players will see them: name, picture, bet limits in Fishkas and the biggest win the
 * wallet pays for one round. Shown to players only while on and their provider is on and connected.
 */
class SlotGameResource extends Resource
{
    protected static ?string $model = SlotGame::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Casino';

    protected static ?string $navigationLabel = 'Slot games';

    protected static ?int $navigationSort = 21;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Select::make('slot_provider_id')->label('Provider')->relationship('provider', 'name')->required(),
            TextInput::make('provider_game_id')->label('Provider\'s game id')->required()->maxLength(120),
            TextInput::make('slug')->required()->maxLength(60)->alphaDash()->unique(ignoreRecord: true),
            Translatable::input('name', 'Name', maxLength: 60),
            FileUpload::make('image_path')->label('Picture')
                ->image()->acceptedFileTypes(GameForm::IMAGE_TYPES)->maxSize(2048)
                ->disk('public')->directory('slots')->visibility('public'),
            Section::make('Fishkas')->columns(3)->schema([
                TextInput::make('min_bet')->label('Smallest bet')->integer()->required()->minValue(1)->default(10),
                TextInput::make('max_bet')->label('Biggest bet')->integer()->required()->gte('min_bet')->default(1000),
                TextInput::make('max_multiplier')->label('Biggest win (× the round\'s bets)')->integer()->required()
                    ->minValue(1)->maxValue(100000)->default(5000)
                    ->helperText('A win above this is refused and listed under Slot wallet calls.'),
            ]),
            Toggle::make('is_enabled')->label('On'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->afterReordering(fn (array $order) => app(AuditLogger::class)->record('slot_game.reordered', null, ['order' => $order], auth('admin')->user()))
            ->columns([
                ImageColumn::make('image_path')->label('Picture')->disk('public'),
                TextColumn::make('name'),
                TextColumn::make('provider.name')->label('Provider'),
                TextColumn::make('min_bet')->label('Bets')->formatStateUsing(fn (SlotGame $record) => "{$record->min_bet}–{$record->max_bet}"),
                IconColumn::make('is_enabled')->label('On')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSlotGames::route('/'),
            'create' => CreateSlotGame::route('/create'),
            'edit' => EditSlotGame::route('/{record}/edit'),
        ];
    }
}
