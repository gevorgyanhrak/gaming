<?php

namespace App\Filament\Resources\PaymentOrders;

use App\Enums\PaymentStatus;
use App\Filament\Resources\PaymentOrders\Pages\ListPaymentOrders;
use App\Models\PaymentOrder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Chip package orders (ADR 0027) with their status. Read only: an order changes only through its payment provider's
 * verified calls — nobody can mark one paid from here.
 */
class PaymentOrderResource extends Resource
{
    protected static ?string $model = PaymentOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Payments';

    protected static ?string $navigationLabel = 'Payment orders';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
                TextColumn::make('public_id')->label('Order')->searchable()->copyable(),
                TextColumn::make('user.username')->label('Player')->searchable(),
                TextColumn::make('package.name')->label('Package'),
                TextColumn::make('price_cents')->label('Price')
                    ->formatStateUsing(fn (int $state, PaymentOrder $record) => number_format($state / 100, 2).' '.$record->currency),
                TextColumn::make('chips')->label('Fishkas')->numeric(),
                TextColumn::make('status')->badge()->color(fn (PaymentStatus $state) => match ($state) {
                    PaymentStatus::Paid => 'success',
                    PaymentStatus::Pending, PaymentStatus::Processing => 'warning',
                    PaymentStatus::Refunded => 'info',
                    default => 'danger',
                }),
                TextColumn::make('provider'),
                TextColumn::make('provider_reference')->label('Provider ref')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('failure_reason')->label('Reason')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('paid_at')->dateTime()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(PaymentStatus::cases())->mapWithKeys(fn (PaymentStatus $s) => [$s->value => ucfirst($s->value)])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPaymentOrders::route('/')];
    }
}
