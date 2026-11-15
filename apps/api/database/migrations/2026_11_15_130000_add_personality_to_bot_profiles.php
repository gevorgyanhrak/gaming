<?php

use App\Enums\BotPersonality;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Each AI player gets a personality (pace and, where a game allows, style); existing ones one by their name. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_profiles', function (Blueprint $table) {
            $table->string('personality', 16)->default(BotPersonality::Steady->value)->after('level');
        });
        $values = implode("','", array_map(fn (BotPersonality $p) => $p->value, BotPersonality::cases()));
        DB::statement("ALTER TABLE bot_profiles ADD CONSTRAINT bot_profiles_personality_check CHECK (personality IN ('{$values}'))");

        foreach (DB::table('bot_profiles')->get(['id', 'name']) as $bot) {
            DB::table('bot_profiles')->where('id', $bot->id)->update(['personality' => BotPersonality::for((string) $bot->name)->value]);
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE bot_profiles DROP CHECK bot_profiles_personality_check');
        Schema::table('bot_profiles', fn (Blueprint $table) => $table->dropColumn('personality'));
    }
};
