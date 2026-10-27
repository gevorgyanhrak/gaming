<?php

namespace App\Filament\Resources\Games\Schemas;

use App\Enums\BotFallbackMode;
use App\Filament\Support\Translatable;
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
                    ]),
                Section::make('Availability')
                    ->columnSpan(1)
                    ->schema([
                        TextInput::make('slug')->disabled(),
                        Toggle::make('is_visible')->label('Shown in the catalog'),
                        // Becomes switchable once the game's engine is registered (Phase 6+).
                        Toggle::make('is_enabled')->label('Playable')
                            ->disabled()
                            ->helperText('Can be switched on once this game’s engine is released.'),
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
                Section::make('Bots')
                    ->columnSpan(1)
                    ->description('Takes effect when matchmaking launches.')
                    ->schema([
                        Toggle::make('bots_enabled')->label('Bot opponents available'),
                        Select::make('bot_fallback_mode')->label('When no opponent is found')
                            ->required()
                            ->options([
                                BotFallbackMode::Offer->value => 'Offer a bot game',
                                BotFallbackMode::Auto->value => 'Start a bot game automatically',
                                BotFallbackMode::Off->value => 'Keep waiting for a person',
                            ]),
                        TextInput::make('bot_fallback_timeout_seconds')->label('Bot fallback after (seconds)')
                            ->numeric()->integer()->minValue(5)->maxValue(600)
                            ->placeholder('Platform default')
                            ->helperText('Leave empty to use the default from Settings.'),
                    ]),
            ]);
    }
}
