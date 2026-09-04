<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resource_groups', function (Blueprint $table) {
            $table->bigInteger('default_hourly_rate_amount')->nullable()->after('is_active');
            $table->char('default_currency', 3)->nullable()->after('default_hourly_rate_amount');
        });

        DB::statement('ALTER TABLE resource_groups ADD CONSTRAINT resource_groups_default_rate_check CHECK (default_hourly_rate_amount IS NULL OR default_hourly_rate_amount >= 0)');
    }

    public function down(): void
    {
        Schema::table('resource_groups', function (Blueprint $table) {
            $table->dropColumn(['default_hourly_rate_amount', 'default_currency']);
        });
    }
};
