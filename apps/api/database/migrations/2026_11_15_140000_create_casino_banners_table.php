<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Casino banners (Phase B): promotions on the home page, managed in the admin panel — picture, title, text, an
 * optional link, shown or hidden, an optional date window, and their order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('casino_banners', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->json('description')->nullable();
            $table->json('cta')->nullable();
            $table->string('image_path')->nullable();
            $table->string('link', 500)->nullable();
            $table->boolean('is_visible')->default(true);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->datetimes();

            $table->index(['is_visible', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('casino_banners');
    }
};
