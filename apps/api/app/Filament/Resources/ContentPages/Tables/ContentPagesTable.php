<?php

namespace App\Filament\Resources\ContentPages\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContentPagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->description(fn ($record) => '/pages/'.$record->slug),
                IconColumn::make('is_published')->label('Published')->boolean(),
                TextColumn::make('updated_at')->label('Last changed')->since()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
