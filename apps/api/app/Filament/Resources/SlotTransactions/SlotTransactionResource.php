<?php

namespace App\Filament\Resources\SlotTransactions;

use App\Filament\Resources\SlotTransactions\Pages\ListSlotTransactions;
use App\Models\SlotTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Every wallet call slot providers made — bets, wins, rollbacks — accepted or refused with the reason (the error
 * list). Read only.
 */
class SlotTransactionResource extends Resource
{
    protected static ?string $model = SlotTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Casino';

    protected static ?string $navigationLabel = 'Slot wallet calls';

    protected static ?int $navigationSort = 22;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
                TextColumn::make('provider.name')->label('Provider'),
                TextColumn::make('user.username')->label('Player')->placeholder('—'),
                TextColumn::make('session.game.name')->label('Game')->placeholder('—'),
                TextColumn::make('type')->badge(),
                TextColumn::make('amount')->numeric(),
                TextColumn::make('status')->badge()->color(fn (string $state) => $state === SlotTransaction::OK ? 'success' : 'danger'),
                TextColumn::make('error')->placeholder('—'),
                TextColumn::make('round_id')->label('Round')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('provider_tx_id')->label('Provider tx')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options([SlotTransaction::OK => 'Accepted', SlotTransaction::REFUSED => 'Refused (errors)']),
                SelectFilter::make('type')->options(['bet' => 'Bet', 'win' => 'Win', 'rollback' => 'Rollback']),
                SelectFilter::make('slot_provider_id')->label('Provider')->relationship('provider', 'name'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListSlotTransactions::route('/')];
    }
}
