<?php

namespace App\Filament\Resources\Games\RelationManagers;

use App\Filament\Support\Translatable;
use App\Filament\Support\TranslationData;
use App\Models\GameMode;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class ModesRelationManager extends RelationManager
{
    protected static string $relationship = 'modes';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('slug')
                    ->required()->alphaDash()->maxLength(50)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('game_id', $this->getOwnerRecord()->getKey())),
                Translatable::input('name', 'Name', maxLength: 60),
                Translatable::textarea('description', 'Description', required: false, rows: 2),
                Grid::make(2)->schema([
                    TextInput::make('min_players')->numeric()->integer()->required()->minValue(1)
                        ->maxValue(fn () => $this->getOwnerRecord()->getAttribute('max_players')),
                    TextInput::make('max_players')->numeric()->integer()->required()->gte('min_players')
                        ->maxValue(fn () => $this->getOwnerRecord()->getAttribute('max_players')),
                ]),
                Grid::make(4)->schema([
                    Toggle::make('is_enabled')->label('Enabled')->default(true),
                    Toggle::make('matchmaking_enabled')->label('Matchmaking')->default(true),
                    Toggle::make('private_rooms_enabled')->label('Private rooms')->default(true),
                    Toggle::make('bots_allowed')->label('Bots allowed')->default(true),
                ]),
                KeyValue::make('config')
                    ->label('Game options')
                    ->helperText('Options read by the game engine (e.g. time control). Validated when the engine ships.')
                    ->columnSpanFull(),
                TextInput::make('sort_order')->numeric()->integer()->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('slug')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->description(fn (GameMode $record) => $record->slug),
                TextColumn::make('players')->state(fn (GameMode $record) => $record->min_players === $record->max_players
                    ? (string) $record->min_players
                    : "{$record->min_players}–{$record->max_players}"),
                IconColumn::make('is_enabled')->label('Enabled')->boolean(),
                IconColumn::make('matchmaking_enabled')->label('Matchmaking')->boolean(),
                IconColumn::make('private_rooms_enabled')->label('Private rooms')->boolean(),
                IconColumn::make('bots_allowed')->label('Bots')->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->mutateDataUsing(fn (array $data) => TranslationData::clean($data)),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, GameMode $record) => TranslationData::fill($record, $data))
                    ->mutateDataUsing(fn (array $data) => TranslationData::clean($data)),
                DeleteAction::make(),
            ]);
    }
}
