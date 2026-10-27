<?php

namespace App\Filament\Resources\BotProfiles\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BotProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('avatar_path')->label('')->disk('public')->circular(),
                TextColumn::make('name')->searchable()->sortable(),
                IconColumn::make('is_enabled')->label('Enabled')->boolean(),
                TextColumn::make('game_profiles_count')->counts('gameProfiles')->label('Games'),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_enabled')->label('Enabled'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
