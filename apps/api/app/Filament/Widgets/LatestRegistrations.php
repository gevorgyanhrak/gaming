<?php

namespace App\Filament\Widgets;

use App\Enums\UserStatus;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestRegistrations extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent registrations';

    public static function canView(): bool
    {
        return auth('admin')->user()?->can('manage users') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => User::query()->latest()->limit(10))
            ->paginated(false)
            ->recordUrl(fn (User $record) => UserResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('username'),
                TextColumn::make('email')->placeholder('—'),
                TextColumn::make('phone')->placeholder('—'),
                TextColumn::make('status')->badge()->color(fn (UserStatus $state) => UsersTable::statusColor($state)),
                TextColumn::make('created_at')->label('Registered')->since(),
            ]);
    }
}
