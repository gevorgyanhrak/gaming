<?php

namespace App\Filament\Resources\Games\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GamesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('icon_path')->label('')->disk('public')->square(),
                TextColumn::make('name')->description(fn ($record) => $record->slug),
                IconColumn::make('is_visible')->label('Visible')->boolean(),
                IconColumn::make('is_enabled')->label('Playable')->boolean(),
                IconColumn::make('bots_enabled')->label('Bots')->boolean(),
                TextColumn::make('modes_count')->counts('modes')->label('Modes'),
                TextColumn::make('players')->state(fn ($record) => $record->min_players === $record->max_players
                    ? (string) $record->min_players
                    : "{$record->min_players}–{$record->max_players}"),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
