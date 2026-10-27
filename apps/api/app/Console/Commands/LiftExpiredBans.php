<?php

namespace App\Console\Commands;

use App\Platform\Moderation\UserModeration;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:lift-expired-bans')]
#[Description('Reactivate users whose temporary bans have expired')]
class LiftExpiredBans extends Command
{
    public function handle(UserModeration $moderation): int
    {
        $count = $moderation->liftExpiredBans();

        if ($count > 0) {
            $this->info("Reactivated {$count} user(s).");
        }

        return self::SUCCESS;
    }
}
