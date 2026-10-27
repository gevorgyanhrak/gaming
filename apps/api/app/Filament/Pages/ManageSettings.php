<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Platform\Audit\AuditLogger;
use App\Platform\Settings\Settings;
use App\Platform\Settings\SettingsCatalog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use libphonenumber\PhoneNumberUtil;
use Locale;
use UnitEnum;

/**
 * Platform settings (SettingsCatalog). Field names are setting keys; "general.platform_name" is
 * stored at data.general.platform_name in the form state.
 *
 * @property-read Schema $form
 */
class ManageSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'Platform settings';

    protected static ?int $navigationSort = 10;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->can('manage settings') ?? false;
    }

    public function mount(Settings $settings): void
    {
        $this->form->fill(Arr::undot($settings->all()));
    }

    public function form(Schema $schema): Schema
    {
        $locales = array_combine(SettingsCatalog::AVAILABLE_LOCALES, array_map(
            fn (string $locale) => Locale::getDisplayLanguage($locale, $locale),
            SettingsCatalog::AVAILABLE_LOCALES,
        ));

        $countries = collect(PhoneNumberUtil::getInstance()->getSupportedRegions())
            ->mapWithKeys(fn (string $code) => [$code => Locale::getDisplayRegion('-'.$code, 'en')." ({$code})"])
            ->sort()
            ->all();

        return $schema
            ->statePath('data')
            ->components([
                Section::make('General')->columns(2)->schema([
                    TextInput::make('general.platform_name')->label('Platform name')->required()->maxLength(60),
                    Select::make('general.default_locale')->label('Default language')->options($locales)->required(),
                    CheckboxList::make('general.supported_locales')->label('Languages offered to players')
                        ->options($locales)->required()->columns(3),
                    FileUpload::make('general.logo')->label('Logo')
                        ->image()->acceptedFileTypes(['image/png', 'image/webp'])->maxSize(1024)
                        ->disk('public')->directory('branding')->visibility('public'),
                    FileUpload::make('general.favicon')->label('Favicon')
                        // Raster only: SVG can carry scripts and would be served from the API origin.
                        ->acceptedFileTypes(['image/png', 'image/x-icon', 'image/vnd.microsoft.icon'])->maxSize(256)
                        ->disk('public')->directory('branding')->visibility('public'),
                ]),
                Section::make('Access')->columns(2)->schema([
                    Toggle::make('access.maintenance_mode')->label('Maintenance mode')
                        ->helperText('Players see a maintenance screen; staff can still sign in and use the platform.'),
                    Textarea::make('access.maintenance_message')->label('Maintenance message')->maxLength(300)
                        ->placeholder('We are doing some maintenance. Please check back soon.'),
                    Toggle::make('access.registration_enabled')->label('Registration open'),
                    Toggle::make('access.require_email_verification')->label('Email verification required to play')
                        ->helperText('Enforced when gameplay launches.'),
                    Toggle::make('access.require_phone_verification')->label('Phone verification required to play')
                        ->helperText('Enforced when gameplay launches.'),
                    Select::make('access.phone_allowed_countries')->label('Countries allowed for phone sign-up')
                        ->multiple()->searchable()->options($countries)
                        ->helperText('Empty allows every country. Limiting this reduces SMS cost and fraud.'),
                ]),
                Section::make('Matchmaking')->columns(2)->description('Takes effect when matchmaking launches.')->schema([
                    TextInput::make('matchmaking.default_bot_timeout_seconds')->label('Offer a bot after (seconds)')
                        ->numeric()->integer()->required()->minValue(5)->maxValue(600)
                        ->helperText('Default for games without their own timeout.'),
                    TextInput::make('matchmaking.max_concurrent_games')->label('Games a player can play at once')
                        ->numeric()->integer()->required()->minValue(1)->maxValue(10),
                ]),
                Section::make('Security')->columns(2)->schema([
                    TextInput::make('security.session_lifetime_minutes')->label('Session lifetime (minutes)')
                        ->numeric()->integer()->required()->minValue(15)->maxValue(43200)
                        ->helperText('How long a player stays signed in without activity.'),
                ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Save settings')->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(Settings $settings, AuditLogger $audit): void
    {
        $state = $this->form->getState();
        $values = [];
        foreach (array_keys(SettingsCatalog::all()) as $key) {
            $values[$key] = data_get($state, $key);
        }

        /** @var User $actor */
        $actor = auth('admin')->user();

        try {
            $changes = $settings->update($values, $actor);
        } catch (ValidationException $e) {
            // Report errors on the form fields (their state lives under "data.").
            throw ValidationException::withMessages(
                collect($e->errors())->mapWithKeys(fn ($messages, $key) => ["data.{$key}" => $messages])->all(),
            );
        }

        if ($changes !== []) {
            $audit->record('settings.updated', changes: $changes, actor: $actor);
        }

        Notification::make()->success()->title($changes === [] ? 'Nothing changed.' : 'Settings saved.')->send();
    }
}
