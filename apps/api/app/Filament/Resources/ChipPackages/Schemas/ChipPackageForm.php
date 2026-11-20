<?php

namespace App\Filament\Resources\ChipPackages\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ChipPackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Package')
                    ->description('What players get for the price once a payment provider is connected (Settings → Payments). Orders keep the price and Fishkas they were bought at.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->required()->maxLength(60),
                        TextInput::make('slug')->required()->maxLength(40)->alphaDash()->unique(ignoreRecord: true),
                        TextInput::make('price_cents')->label('Price (cents)')->required()->numeric()->integer()->minValue(1)->maxValue(10000000)
                            ->helperText('e.g. 1000 = $10.00'),
                        TextInput::make('currency')->required()->length(3)->default('USD'),
                        TextInput::make('chips')->label('Fishkas')->required()->numeric()->integer()->minValue(1)->maxValue(100000000),
                        TextInput::make('bonus_chips')->label('Bonus Fishkas')->required()->numeric()->integer()->minValue(0)->maxValue(100000000)->default(0),
                        TextInput::make('sort_order')->label('Order')->required()->numeric()->integer()->default(0),
                        Toggle::make('is_recommended')->label('Recommended (highlighted)'),
                        Toggle::make('is_enabled')->label('On sale')->default(true),
                    ]),
            ]);
    }
}
