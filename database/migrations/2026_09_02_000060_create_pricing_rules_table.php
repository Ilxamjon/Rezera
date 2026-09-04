<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignUuid('resource_id')->nullable()->constrained('resources')->cascadeOnDelete();
            $table->foreignUuid('resource_group_id')->nullable()->constrained('resource_groups')->cascadeOnDelete();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->string('pricing_type', 32);
            $table->bigInteger('price');
            $table->char('currency', 3)->default('UZS');
            $table->smallInteger('day_of_week')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->date('specific_date')->nullable();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->jsonb('metadata')->nullable();
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index(['business_id', 'is_active']);
            $table->index(['resource_id', 'is_active']);
            $table->index(['resource_group_id', 'is_active']);
            $table->index('specific_date');
            $table->index(['starts_at', 'ends_at']);
            $table->index(['day_of_week', 'start_time', 'end_time']);
            $table->index('priority');
        });

        DB::statement("ALTER TABLE pricing_rules ADD CONSTRAINT pricing_rules_pricing_type_check CHECK (pricing_type IN ('hourly', 'fixed'))");
        DB::statement('ALTER TABLE pricing_rules ADD CONSTRAINT pricing_rules_price_check CHECK (price >= 0)');
        DB::statement('ALTER TABLE pricing_rules ADD CONSTRAINT pricing_rules_weekday_check CHECK (day_of_week IS NULL OR (day_of_week BETWEEN 1 AND 7))');
        DB::statement('ALTER TABLE pricing_rules ADD CONSTRAINT pricing_rules_scope_check CHECK (
            (resource_id IS NOT NULL AND resource_group_id IS NULL) OR
            (resource_id IS NULL AND resource_group_id IS NOT NULL) OR
            (resource_id IS NULL AND resource_group_id IS NULL)
        )');
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
    }
};
