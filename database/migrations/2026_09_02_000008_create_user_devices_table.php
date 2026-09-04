<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('device_id', 128);
            $table->string('platform', 16);
            $table->string('push_token', 512)->nullable();
            $table->string('app_version', 32)->nullable();
            $table->string('locale', 8)->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['user_id', 'device_id']);
            $table->index(['user_id', 'is_active']);
            $table->index('push_token');
        });

        DB::statement("ALTER TABLE user_devices ADD CONSTRAINT user_devices_platform_check CHECK (platform IN ('android', 'ios', 'web'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
