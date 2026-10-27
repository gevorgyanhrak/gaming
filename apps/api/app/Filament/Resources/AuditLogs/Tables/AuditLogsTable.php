<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Models\AuditLog;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
                TextColumn::make('actor.username')->label('Who')->placeholder('System')->searchable(),
                TextColumn::make('action')->badge()->searchable(),
                TextColumn::make('subject')->state(fn (AuditLog $record) => $record->subject_type === null
                    ? null
                    : $record->subject_type.($record->subject_id ? " #{$record->subject_id}" : ''))
                    ->placeholder('—'),
                TextColumn::make('ip')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->options(fn () => AuditLog::query()->distinct()->orderBy('action')->pluck('action', 'action')->all()),
                SelectFilter::make('actor')->relationship('actor', 'username')->searchable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
