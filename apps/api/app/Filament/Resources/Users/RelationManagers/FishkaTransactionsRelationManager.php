<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Enums\FishkaTransactionType;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * The user's Fishka ledger, read-only. Balances change only through the actions on the page
 * (FishkaWallets::adjust), never by editing entries.
 */
class FishkaTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'fishkaTransactions';

    protected static ?string $title = 'Fishka transactions';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
                TextColumn::make('type')->badge()
                    ->formatStateUsing(fn (FishkaTransactionType $state) => str_replace('_', ' ', $state->value)),
                TextColumn::make('amount')->numeric()
                    ->color(fn (int $state) => $state > 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn (int $state) => ($state > 0 ? '+' : '').number_format($state)),
                TextColumn::make('balance_before')->label('Before')->numeric(),
                TextColumn::make('balance_after')->label('After')->numeric(),
                TextColumn::make('description')->placeholder('—')->wrap(),
                TextColumn::make('reason')->placeholder('—')->wrap(),
                TextColumn::make('actor.username')->label('Admin')->placeholder('—'),
                TextColumn::make('reference_id')->label('Game')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')->options(collect(FishkaTransactionType::cases())
                    ->mapWithKeys(fn (FishkaTransactionType $type) => [$type->value => str_replace('_', ' ', $type->value)])
                    ->all()),
            ]);
    }
}
