<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Admin-panel two-factor authentication (Filament); both stored encrypted.
            $table->text('app_authentication_secret')->nullable()->after('remember_token');
            $table->text('app_authentication_recovery_codes')->nullable()->after('app_authentication_secret');
            // Set at registration when a published terms page exists.
            $table->dateTime('terms_accepted_at')->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['app_authentication_secret', 'app_authentication_recovery_codes', 'terms_accepted_at']);
        });
    }
};
