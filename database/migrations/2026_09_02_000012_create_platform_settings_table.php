<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 64)->unique();
            $table->text('value')->nullable();
            $table->string('type', 32)->default('string');
            $table->string('description', 500)->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE platform_settings ADD CONSTRAINT platform_settings_type_check CHECK (type IN ('string', 'boolean', 'integer', 'json'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
