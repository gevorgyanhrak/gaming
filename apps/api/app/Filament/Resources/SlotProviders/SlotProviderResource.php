<?php

namespace App\Filament\Resources\SlotProviders;

use App\Filament\Resources\SlotProviders\Pages\CreateSlotProvider;
use App\Filament\Resources\SlotProviders\Pages\EditSlotProvider;
use App\Filament\Resources\SlotProviders\Pages\ListSlotProviders;
use App\Models\SlotProvider;
use App\Platform\Slots\SlotAdapters;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Slot providers (docs/research/casino-slots.md). One can be switched on only when the code has its adapter and
 * its wallet secret is set in the environment — so no slot is ever offered without the secure Fishka wallet.
 */
class SlotProviderResource extends Resource
{
    protected static ?string $model = SlotProvider::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static string|UnitEnum|null $navigationGroup = 'Casino';

    protected static ?string $navigationLabel = 'Slot providers';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('name')->required()->maxLength(80),
            TextInput::make('slug')->required()->maxLength(40)->alphaDash()->unique(ignoreRecord: true)
                ->helperText('Must match the adapter in the code.'),
            Toggle::make('is_enabled')->label('On')
                ->helperText('Possible only when the code has this provider\'s adapter and its wallet secret is configured.')
                ->rules([fn (callable $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get) {
                    if ($value && ! app(SlotAdapters::class)->ready((string) $get('slug'))) {
                        $fail('This provider has no adapter or wallet secret yet, so it cannot be switched on.');
                    }
                }]),
            Textarea::make('notes')->rows(3)->helperText('Contract, licence and contact notes for the team.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('slug'),
                IconColumn::make('is_enabled')->label('On')->boolean(),
                IconColumn::make('connected')->label('Adapter ready')->boolean()
                    ->state(fn (SlotProvider $record) => app(SlotAdapters::class)->ready($record->slug)),
                TextColumn::make('games_count')->counts('games')->label('Games'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSlotProviders::route('/'),
            'create' => CreateSlotProvider::route('/create'),
            'edit' => EditSlotProvider::route('/{record}/edit'),
        ];
    }
}
