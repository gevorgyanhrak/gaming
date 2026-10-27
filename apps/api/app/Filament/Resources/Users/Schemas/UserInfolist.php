<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserStatus;
use App\Filament\Resources\Users\Tables\UsersTable;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Account')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('username'),
                        TextEntry::make('status')->badge()->color(fn (UserStatus $state) => UsersTable::statusColor($state)),
                        TextEntry::make('email')->placeholder('Not set')->copyable(),
                        IconEntry::make('email_verified_at')->label('Email verified')->boolean()
                            ->state(fn ($record) => $record->email_verified_at !== null),
                        TextEntry::make('phone')->placeholder('Not set')->copyable(),
                        IconEntry::make('phone_verified_at')->label('Phone verified')->boolean()
                            ->state(fn ($record) => $record->phone_verified_at !== null),
                        TextEntry::make('roles.name')->label('Roles')->badge()->placeholder('Player'),
                        TextEntry::make('locale')->placeholder('—'),
                    ]),
                Section::make('Profile')
                    ->columnSpan(1)
                    ->schema([
                        ImageEntry::make('profile.avatar_path')->label('Avatar')->disk('public')->circular()->placeholder('No avatar'),
                        TextEntry::make('profile.display_name')->label('Display name')->placeholder('—'),
                        TextEntry::make('profile.country')->label('Country')->placeholder('—'),
                        TextEntry::make('profile.bio')->label('Bio')->placeholder('—'),
                    ]),
                Section::make('Activity')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('created_at')->label('Registered')->dateTime(),
                        TextEntry::make('last_active_at')->label('Last active')->dateTime()->placeholder('Never'),
                        TextEntry::make('terms_accepted_at')->label('Terms accepted')->dateTime()->placeholder('—'),
                    ]),
                Section::make('Ban history')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('bans')
                            ->hiddenLabel()
                            ->placeholder('No bans.')
                            ->columns(5)
                            ->schema([
                                TextEntry::make('reason'),
                                TextEntry::make('bannedBy.username')->label('By')->placeholder('—'),
                                TextEntry::make('created_at')->label('Banned')->dateTime(),
                                TextEntry::make('expires_at')->label('Expires')->dateTime()->placeholder('Permanent'),
                                TextEntry::make('revoked_at')->label('Lifted')->dateTime()->placeholder('—'),
                            ]),
                    ]),
            ]);
    }
}
