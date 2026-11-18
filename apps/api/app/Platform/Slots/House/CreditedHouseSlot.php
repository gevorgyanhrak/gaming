<?php

namespace App\Platform\Slots\House;

/** A house slot with its own credit line (where its art and maths come from), shown under the game. */
interface CreditedHouseSlot extends HouseSlot
{
    public function credit(): string;
}
