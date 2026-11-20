<?php

use App\Platform\Settings\Settings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * HayBet item 7 (owner, 2026-11-20): the platform is now called HayBet. The name is an admin setting
 * (general.platform_name); this sets it unless an administrator already chose another name than the old defaults.
 */
return new class extends Migration
{
    private const OLD = ['Game Platform', 'Laravel', 'Nardi Club'];

    public function up(): void
    {
        $current = DB::table('platform_settings')->where('key', 'general.platform_name')->value('value');
        if ($current !== null && ! in_array(json_decode($current, true), self::OLD, true)) {
            return;
        }
        DB::table('platform_settings')->updateOrInsert(
            ['key' => 'general.platform_name'],
            ['value' => json_encode('HayBet'), 'created_at' => now(), 'updated_at' => now()],
        );
        app(Settings::class)->flush();
    }

    public function down(): void
    {
        // The name stays an admin setting; nothing to undo automatically.
    }
};
