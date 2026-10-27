<?php

namespace App\Filament\Resources\BotProfiles;

use App\Filament\Resources\BotProfiles\Pages\CreateBotProfile;
use App\Filament\Resources\BotProfiles\Pages\EditBotProfile;
use App\Filament\Resources\BotProfiles\Pages\ListBotProfiles;
use App\Filament\Resources\BotProfiles\Schemas\BotProfileForm;
use App\Filament\Resources\BotProfiles\Tables\BotProfilesTable;
use App\Models\BotProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BotProfileResource extends Resource
{
    protected static ?string $model = BotProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static string|UnitEnum|null $navigationGroup = 'Games';

    protected static ?string $navigationLabel = 'Bots';

    protected static ?string $modelLabel = 'bot';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return BotProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BotProfilesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBotProfiles::route('/'),
            'create' => CreateBotProfile::route('/create'),
            'edit' => EditBotProfile::route('/{record}/edit'),
        ];
    }
}
