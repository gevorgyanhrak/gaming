<?php

namespace App\Filament\Resources\CasinoBanners;

use App\Filament\Resources\CasinoBanners\Pages\CreateCasinoBanner;
use App\Filament\Resources\CasinoBanners\Pages\EditCasinoBanner;
use App\Filament\Resources\CasinoBanners\Pages\ListCasinoBanners;
use App\Filament\Resources\Games\Schemas\GameForm;
use App\Filament\Support\Translatable;
use App\Models\CasinoBanner;
use App\Platform\Audit\AuditLogger;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The promotion banners on the casino home: picture, title, text, link, shown or hidden, an optional date window,
 * and their order (drag to reorder). Changes are audited. Banners promote games and free bonuses only — never real
 * money, purchases or VIP.
 */
class CasinoBannerResource extends Resource
{
    /** A path inside the app ("/games/poker", never "//host") or an https address. */
    public const LINK_PATTERN = '#^(/(?!/)\S*|https://\S+)$#';

    protected static ?string $model = CasinoBanner::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Casino banners';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Translatable::input('title', 'Title', maxLength: 80),
            Translatable::textarea('description', 'Text', required: false, rows: 2),
            Translatable::input('cta', 'Button label', required: false, maxLength: 30),
            TextInput::make('link')->label('Link')->maxLength(500)
                ->regex(self::LINK_PATTERN)
                ->helperText('A page of the site, like /games/poker, or an https:// address. Leave empty for no link.'),
            FileUpload::make('image_path')->label('Picture (wide, about 3:1)')
                ->image()->acceptedFileTypes(GameForm::IMAGE_TYPES)->maxSize(3072)
                ->disk('public')->directory('banners')->visibility('public'),
            Section::make('When it shows')->columns(3)->schema([
                Toggle::make('is_visible')->label('Visible')->default(true),
                DateTimePicker::make('starts_at')->label('From (optional)'),
                DateTimePicker::make('ends_at')->label('Until (optional)')->after('starts_at'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->afterReordering(fn (array $order) => app(AuditLogger::class)->record('casino_banner.reordered', null, ['order' => $order], auth('admin')->user()))
            ->columns([
                ImageColumn::make('image_path')->label('Picture')->disk('public'),
                TextColumn::make('title'),
                TextColumn::make('link')->limit(40),
                IconColumn::make('is_visible')->label('Visible')->boolean(),
                TextColumn::make('starts_at')->label('From')->dateTime()->placeholder('—'),
                TextColumn::make('ends_at')->label('Until')->dateTime()->placeholder('—'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCasinoBanners::route('/'),
            'create' => CreateCasinoBanner::route('/create'),
            'edit' => EditCasinoBanner::route('/{record}/edit'),
        ];
    }
}
