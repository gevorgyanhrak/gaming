<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            // Translatable: {"en": "...", "ru": "...", "hy": "..."}
            $table->json('title');
            $table->json('body');
            $table->boolean('is_published')->default(false);
            $table->datetimes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_pages');
    }
};
