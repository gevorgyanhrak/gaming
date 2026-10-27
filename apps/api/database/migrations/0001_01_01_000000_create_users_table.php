<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            // Case-insensitive collation makes these unique regardless of letter case.
            $table->string('username', 20)->unique();
            $table->string('email')->nullable()->unique();
            $table->string('phone', 16)->nullable()->unique()->comment('E.164');
            // Nullable for future social-login-only accounts.
            $table->string('password')->nullable();
            $table->dateTime('email_verified_at')->nullable();
            $table->dateTime('phone_verified_at')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->string('locale', 10)->nullable();
            $table->dateTime('last_active_at')->nullable()->index();
            $table->rememberToken();
            $table->datetimes();

            $table->index('created_at');
        });

        DB::statement('ALTER TABLE users ADD CONSTRAINT users_contact_check CHECK (email IS NOT NULL OR phone IS NOT NULL)');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active', 'deactivated', 'banned'))");

        Schema::create('user_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('display_name', 40)->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('bio', 500)->nullable();
            $table->char('country', 2)->nullable();
            $table->json('preferences')->nullable();
            $table->datetimes();
        });

        Schema::create('verification_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 10);
            $table->string('destination');
            $table->string('purpose', 20);
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('expires_at');
            $table->dateTime('consumed_at')->nullable();
            $table->dateTime('created_at');

            $table->index(['user_id', 'purpose', 'channel']);
            $table->index(['destination', 'created_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_codes');
        Schema::dropIfExists('user_profiles');
        Schema::dropIfExists('users');
    }
};
