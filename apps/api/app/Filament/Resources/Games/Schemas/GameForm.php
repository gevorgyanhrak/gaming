<?php

namespace App\Filament\Resources\Games\Schemas;

use App\Enums\BotFallbackMode;
use App\Filament\Support\Translatable;
use App\Gameplay\GameRegistry;
use App\Models\Game;
use App\Models\GameCategory;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GameForm
{
    /** Raster formats only: SVG can carry scripts and would be served from the API origin. */
    public const IMAGE_TYPES = ['image/png', 'image/jpeg', 'image/webp'];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Catalog')
                    ->columnSpan(2)
                    ->schema([
                        Translatable::input('name', 'Name', maxLength: 60),
                        Translatable::textarea('description', 'Description', rows: 3),
                        Translatable::textarea('rules', 'Rules', rows: 10, helperText: 'Paragraphs are separated by a blank line.'),
                        Translatable::textarea('maintenance_message', 'Maintenance message', required: false, rows: 2, helperText: 'Shown to players while the game is in maintenance. Empty: a standard message.'),
                    ]),
                Section::make('Availability')
                    ->columnSpan(1)
                    ->schema([
                        TextInput::make('slug')->disabled(),
                        Select::make('game_category_id')->label('Category')
                            ->relationship('category', 'slug')
                            ->getOptionLabelFromRecordUsing(fn (GameCategory $record) => $record->name)
                            ->preload()
                            ->placeholder('No category'),
                        Toggle::make('is_visible')->label('Shown in the catalog'),
                        Toggle::make('maintenance')->label('Maintenance mode')
                            ->helperText('Shown with the message below; no new games. Games under way go on.'),
                        // A game can only be played once its engine is registered (app/Games/*).
                        Toggle::make('is_enabled')->label('Playable')
                            ->disabled(fn (?Game $record) => $record === null || ! app(GameRegistry::class)->has($record->slug))
                            ->helperText(fn (?Game $record) => $record !== null && app(GameRegistry::class)->has($record->slug)
                                ? 'Players can find and play this game.'
                                : 'Can be switched on once this game’s engine is released.'),
                        TextInput::make('min_players')->label('Minimum players')->disabled(),
                        TextInput::make('max_players')->label('Maximum players')->disabled(),
                    ]),
                Section::make('Images')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        FileUpload::make('icon_path')->label('Icon')
                            ->image()->acceptedFileTypes(self::IMAGE_TYPES)->maxSize(1024)
                            ->disk('public')->directory('games')->visibility('public'),
                        FileUpload::make('thumbnail_path')->label('Thumbnail (16:9)')
                            ->image()->acceptedFileTypes(self::IMAGE_TYPES)->maxSize(2048)
                            ->disk('public')->directory('games')->visibility('public'),
                    ]),
                Section::make('AI players')
                    ->columnSpan(1)
                    ->description('AI always plays at the highest level the game has bots for. Players never see who is AI.')
                    ->schema([
                        Toggle::make('bots_enabled')->label('AI players available'),
                        Select::make('bot_fallback_mode')->label('When not enough people are found')
                            ->required()
                            ->options([
                                BotFallbackMode::Auto->value => 'Fill the empty seats with AI',
                                BotFallbackMode::Off->value => 'Keep waiting for people',
                            ]),
                        TextInput::make('bot_fallback_timeout_seconds')->label('AI fills the seats after (seconds)')
                            ->numeric()->integer()->minValue(5)->maxValue(600)
                            ->placeholder('Platform default')
                            ->helperText('Leave empty to use the default from Settings.'),
                    ]),
            ]);
    }
}
