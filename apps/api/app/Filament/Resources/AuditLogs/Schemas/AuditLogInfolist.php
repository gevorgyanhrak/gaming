<?php

namespace App\Filament\Resources\AuditLogs\Schemas;

use App\Models\AuditLog;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AuditLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()->columns(3)->schema([
                    TextEntry::make('created_at')->label('When')->dateTime(),
                    TextEntry::make('actor.username')->label('Who')->placeholder('System'),
                    TextEntry::make('action')->badge(),
                    TextEntry::make('subject_type')->label('Subject')->placeholder('—'),
                    TextEntry::make('subject_id')->label('Subject ID')->placeholder('—'),
                    TextEntry::make('ip')->label('IP')->placeholder('—'),
                    TextEntry::make('user_agent')->columnSpanFull()->placeholder('—'),
                ]),
                Section::make('Changes')->schema([
                    KeyValueEntry::make('changes')
                        ->hiddenLabel()
                        ->keyLabel('Field')
                        ->valueLabel('Change')
                        ->state(fn (AuditLog $record) => self::describe($record->changes ?? []))
                        ->placeholder('No details.'),
                ]),
            ]);
    }

    /**
     * Flattens {field: {old, new}} into "old → new" strings for display.
     *
     * @param  array<string, mixed>  $changes
     * @return array<string, string>
     */
    private static function describe(array $changes): array
    {
        return collect($changes)->map(function ($change) {
            if (is_array($change) && array_key_exists('old', $change) && array_key_exists('new', $change)) {
                return self::format($change['old']).' → '.self::format($change['new']);
            }

            return self::format($change);
        })->all();
    }

    private static function format(mixed $value): string
    {
        return match (true) {
            $value === null => '—',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        };
    }
}
