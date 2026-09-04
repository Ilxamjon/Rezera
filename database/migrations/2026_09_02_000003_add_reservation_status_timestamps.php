<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->timestampTz('confirmed_at')->nullable()->after('expires_at');
            $table->timestampTz('no_show_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropColumn(['confirmed_at', 'no_show_at']);
        });
    }
};
