<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Values overridden by administrators. Keys, types, defaults and visibility are defined
        // in code (App\Platform\Settings\SettingsCatalog); a missing row means "use the default".
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            // JSON-encoded value. Secrets never go here (see SettingsCatalog).
            $table->text('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->datetimes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
