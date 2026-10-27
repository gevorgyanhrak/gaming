<?php

namespace App\Filament\Resources\ContentPages\Schemas;

use App\Filament\Support\Translatable;
use App\Models\ContentPage;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContentPageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()->columns(2)->schema([
                    TextInput::make('slug')
                        ->required()->alphaDash()->maxLength(50)->unique(ignoreRecord: true)
                        ->helperText('Address of the page: /pages/{slug}.')
                        ->disabled(fn (?ContentPage $record) => in_array($record?->slug, [ContentPage::TERMS, ContentPage::PRIVACY], true)),
                    Toggle::make('is_published')->label('Published')
                        ->helperText(fn (?ContentPage $record) => $record?->slug === ContentPage::TERMS
                            ? 'While published, new players must accept these terms to register.'
                            : 'Only published pages are shown to players.'),
                ]),
                Section::make()->schema([
                    Translatable::input('title', 'Title', maxLength: 120),
                    Translatable::textarea('body', 'Text', rows: 18, helperText: 'Paragraphs are separated by a blank line.'),
                ]),
            ]);
    }
}
