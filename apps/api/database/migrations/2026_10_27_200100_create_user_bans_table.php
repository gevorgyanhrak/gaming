<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ban history. users.status is the fast copy used on every request.
        Schema::create('user_bans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('banned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500);
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->datetimes();

            $table->index(['user_id', 'revoked_at']);
            $table->index(['expires_at', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_bans');
    }
};
