<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_hours', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->smallInteger('weekday');
            $table->boolean('is_closed')->default(false);
            $table->boolean('is_open_24h')->default(false);
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();

            $table->unique(['business_id', 'weekday']);
            $table->index('business_id');
        });

        DB::statement('ALTER TABLE business_hours ADD CONSTRAINT business_hours_weekday_check CHECK (weekday BETWEEN 1 AND 7)');
        DB::statement('ALTER TABLE business_hours ADD CONSTRAINT business_hours_schedule_check CHECK (
            (is_closed = true AND is_open_24h = false AND opens_at IS NULL AND closes_at IS NULL) OR
            (is_open_24h = true AND is_closed = false AND opens_at IS NULL AND closes_at IS NULL) OR
            (is_closed = false AND is_open_24h = false AND opens_at IS NOT NULL AND closes_at IS NOT NULL)
        )');
    }

    public function down(): void
    {
        Schema::dropIfExists('business_hours');
    }
};
