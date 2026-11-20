<?php

namespace App\Filament\Resources\PaymentOrders\Pages;

use App\Filament\Resources\PaymentOrders\PaymentOrderResource;
use Filament\Resources\Pages\ListRecords;

class ListPaymentOrders extends ListRecords
{
    protected static string $resource = PaymentOrderResource::class;
}
