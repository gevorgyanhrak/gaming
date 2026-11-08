<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Game categories, the shelves of the catalog (TASK-056). Administrators manage them; a game is in at most
 * one. The two launch categories are created here so that every install has them before the games are seeded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->json('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->datetimes();
        });

        Schema::table('games', function (Blueprint $table) {
            $table->foreignId('game_category_id')->nullable()->after('slug')->constrained('game_categories')->nullOnDelete();
        });

        $now = now();
        foreach ([
            ['board', ['en' => 'Board games', 'ru' => 'Настольные игры', 'hy' => 'Սեղանի խաղեր'], 10, ['nardi', 'chess', 'checkers']],
            ['card', ['en' => 'Card games', 'ru' => 'Карточные игры', 'hy' => 'Թղթախաղեր'], 20, ['durak', 'poker']],
        ] as [$slug, $name, $order, $games]) {
            $id = DB::table('game_categories')->insertGetId([
                'slug' => $slug,
                'name' => json_encode($name, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'sort_order' => $order,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('games')->whereIn('slug', $games)->update(['game_category_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropConstrainedForeignId('game_category_id');
        });
        Schema::dropIfExists('game_categories');
    }
};
