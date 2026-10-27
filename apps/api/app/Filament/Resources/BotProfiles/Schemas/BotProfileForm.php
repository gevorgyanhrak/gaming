<?php

namespace App\Filament\Resources\BotProfiles\Schemas;

use App\Enums\BotDifficulty;
use App\Filament\Resources\Games\Schemas\GameForm;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BotProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Bot')
                    ->columnSpan(1)
                    ->schema([
                        TextInput::make('name')
                            ->required()->maxLength(30)->unique(ignoreRecord: true)
                            ->helperText('Shown to players together with a “Bot” badge.'),
                        FileUpload::make('avatar_path')->label('Avatar')
                            ->image()->avatar()->acceptedFileTypes(GameForm::IMAGE_TYPES)->maxSize(1024)
                            ->disk('public')->directory('avatars/bots')->visibility('public'),
                        Toggle::make('is_enabled')->label('Enabled')->default(true),
                    ]),
                Section::make('Games')
                    ->columnSpan(2)
                    ->description('Which games this bot plays, and how strongly.')
                    ->schema([
                        Repeater::make('gameProfiles')
                            ->relationship()
                            ->hiddenLabel()
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel('Add game')
                            ->schema([
                                Select::make('game_id')->label('Game')
                                    ->relationship('game', 'slug')
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name)
                                    ->required()
                                    ->distinct()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                Select::make('difficulty')
                                    ->required()
                                    ->options(collect(BotDifficulty::cases())->mapWithKeys(fn ($d) => [$d->value => ucfirst($d->value)])),
                                Toggle::make('is_enabled')->label('Enabled')->default(true)->inline(false),
                            ]),
                    ]),
            ]);
    }
}
