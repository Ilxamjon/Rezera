<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->restrictOnDelete();
            $table->foreignUuid('resource_group_id')->nullable()->constrained('resource_groups')->nullOnDelete();
            $table->string('name', 120);
            $table->string('code', 64);
            $table->string('resource_type', 32)->default('other');
            $table->string('status', 32)->default('active');
            $table->integer('capacity')->default(1);
            $table->bigInteger('hourly_rate_amount')->nullable();
            $table->char('currency', 3)->default('UZS');
            $table->jsonb('metadata');
            $table->integer('sort_order')->default(0);
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->unique(['id', 'business_id']);
            $table->index('deleted_at');
        });

        DB::statement("ALTER TABLE resources ADD CONSTRAINT resources_type_check CHECK (resource_type IN ('pc', 'console', 'room', 'table', 'desk', 'court', 'other'))");
        DB::statement("ALTER TABLE resources ADD CONSTRAINT resources_status_check CHECK (status IN ('active', 'inactive', 'maintenance'))");
        DB::statement('ALTER TABLE resources ADD CONSTRAINT resources_capacity_check CHECK (capacity > 0)');
        DB::statement('ALTER TABLE resources ADD CONSTRAINT resources_hourly_rate_check CHECK (hourly_rate_amount IS NULL OR hourly_rate_amount >= 0)');
        DB::statement("ALTER TABLE resources ADD CONSTRAINT resources_metadata_is_object CHECK (jsonb_typeof(metadata) = 'object')");
        DB::statement("ALTER TABLE resources ADD CONSTRAINT resources_active_requires_rate CHECK (status <> 'active' OR hourly_rate_amount IS NOT NULL)");
        DB::statement("ALTER TABLE resources ALTER COLUMN metadata SET DEFAULT '{}'::jsonb");
        DB::statement('ALTER TABLE resources ALTER COLUMN metadata SET NOT NULL');

        DB::statement('CREATE UNIQUE INDEX resources_business_code_unique ON resources (business_id, code) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX resources_business_status_idx ON resources (business_id, status) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS resources_business_status_idx');
        DB::statement('DROP INDEX IF EXISTS resources_business_code_unique');
        Schema::dropIfExists('resources');
    }
};
