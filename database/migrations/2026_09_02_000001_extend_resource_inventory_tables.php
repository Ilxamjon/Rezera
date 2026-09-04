<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resource_groups', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('name');
            $table->string('icon', 64)->nullable()->after('description');
            $table->string('color', 7)->nullable()->after('icon');
            $table->boolean('is_active')->default(true)->after('sort_order');
        });

        DB::statement('CREATE INDEX resource_groups_business_active_idx ON resource_groups (business_id, is_active) WHERE deleted_at IS NULL');

        Schema::table('resources', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('code');
            $table->string('image_url', 2048)->nullable()->after('description');
            $table->string('rate_unit', 32)->default('hour')->after('currency');
        });

        DB::statement("ALTER TABLE resources ADD CONSTRAINT resources_rate_unit_check CHECK (rate_unit IN ('hour', 'day'))");
        DB::statement('CREATE INDEX resources_business_group_idx ON resources (business_id, resource_group_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS resources_business_group_idx');
        DB::statement('ALTER TABLE resources DROP CONSTRAINT IF EXISTS resources_rate_unit_check');

        Schema::table('resources', function (Blueprint $table): void {
            $table->dropColumn(['description', 'image_url', 'rate_unit']);
        });

        DB::statement('DROP INDEX IF EXISTS resource_groups_business_active_idx');

        Schema::table('resource_groups', function (Blueprint $table): void {
            $table->dropColumn(['description', 'icon', 'color', 'is_active']);
        });
    }
};
