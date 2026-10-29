<?php

namespace App\Filament\Resources\BotProfiles\Pages;

use App\Filament\Resources\BotProfiles\BotProfileResource;
use App\Gameplay\Bots\BotSelector;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBotProfiles extends ListRecords
{
    protected static string $resource = BotProfileResource::class;

    /** Warns when a game's biggest table would seat the same bot twice at some difficulty. */
    public function getSubheading(): ?string
    {
        $shortages = app(BotSelector::class)->shortages();
        if ($shortages === []) {
            return null;
        }

        return 'Too few bots for a full table, so other bots fill in: '.implode('; ', array_map(
            fn (array $s) => "{$s['game']} · {$s['difficulty']->name}: {$s['bots']} of {$s['needed']}",
            $shortages,
        )).'.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
