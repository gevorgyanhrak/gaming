<?php

use App\Platform\Settings\Settings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * HayBet item 7 (owner, 2026-11-20): the platform is now called HayBet. The name is an admin setting
 * (general.platform_name, default APP_NAME); this renames a stored old default, never a name an administrator chose.
 */
return new class extends Migration
{
    private const OLD = ['Game Platform', 'Laravel', 'Nardi Club'];

    public function up(): void
    {
        // Only a stored old default is renamed. Without a stored name the platform takes it from APP_NAME
        // (now HayBet in .env.example; set it on servers — docs/DEPLOYMENT.md).
        $current = DB::table('platform_settings')->where('key', 'general.platform_name')->value('value');
        if ($current === null || ! in_array(json_decode($current, true), self::OLD, true)) {
            return;
        }
        DB::table('platform_settings')->where('key', 'general.platform_name')
            ->update(['value' => json_encode('HayBet'), 'updated_at' => now()]);
        app(Settings::class)->flush();
    }

    public function down(): void
    {
        // The name stays an admin setting; nothing to undo automatically.
    }
};
