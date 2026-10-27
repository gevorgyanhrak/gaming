<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserStatus;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('username')->searchable()->sortable(),
                TextColumn::make('profile.display_name')->label('Display name')->toggleable(),
                TextColumn::make('email')->searchable()->toggleable(),
                TextColumn::make('phone')->searchable()->toggleable(),
                TextColumn::make('status')->badge()->color(fn (UserStatus $state) => self::statusColor($state)),
                IconColumn::make('email_verified_at')->label('Email verified')->boolean()
                    ->state(fn ($record) => $record->email === null ? null : $record->email_verified_at !== null)
                    ->toggleable(),
                IconColumn::make('phone_verified_at')->label('Phone verified')->boolean()
                    ->state(fn ($record) => $record->phone === null ? null : $record->phone_verified_at !== null)
                    ->toggleable(),
                TextColumn::make('roles.name')->label('Roles')->badge()->toggleable(),
                TextColumn::make('created_at')->label('Registered')->dateTime()->sortable(),
                TextColumn::make('last_active_at')->label('Last active')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(UserStatus::cases())->mapWithKeys(fn ($s) => [$s->value => ucfirst($s->value)])),
                TernaryFilter::make('email_verified_at')->label('Email verified')->nullable(),
                TernaryFilter::make('phone_verified_at')->label('Phone verified')->nullable(),
                SelectFilter::make('roles')->relationship('roles', 'name')->label('Role'),
                Filter::make('registered')
                    ->schema([
                        DatePicker::make('from')->label('Registered from'),
                        DatePicker::make('until')->label('Registered until'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function statusColor(UserStatus $status): string
    {
        return match ($status) {
            UserStatus::Active => 'success',
            UserStatus::Deactivated => 'gray',
            UserStatus::Banned => 'danger',
        };
    }
}
