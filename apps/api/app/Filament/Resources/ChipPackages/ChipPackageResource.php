<?php

namespace App\Filament\Resources\ChipPackages;

use App\Filament\Resources\ChipPackages\Pages\CreateChipPackage;
use App\Filament\Resources\ChipPackages\Pages\EditChipPackage;
use App\Filament\Resources\ChipPackages\Pages\ListChipPackages;
use App\Filament\Resources\ChipPackages\Schemas\ChipPackageForm;
use App\Filament\Resources\ChipPackages\Tables\ChipPackagesTable;
use App\Models\ChipPackage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ChipPackageResource extends Resource
{
    protected static ?string $model = ChipPackage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Payments';

    protected static ?string $navigationLabel = 'Chip packages';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return ChipPackageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChipPackagesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChipPackages::route('/'),
            'create' => CreateChipPackage::route('/create'),
            'edit' => EditChipPackage::route('/{record}/edit'),
        ];
    }
}
