<?php

namespace App\Filament\Resources\ChipPackages\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChipPackagesTable
{
    public static function configure(Table $table): Table
    {
        // No delete: a package with orders must stay; take it off sale instead.
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('price_cents')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('currency')
                    ->searchable(),
                TextColumn::make('chips')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('bonus_chips')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_recommended')
                    ->boolean(),
                IconColumn::make('is_enabled')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
