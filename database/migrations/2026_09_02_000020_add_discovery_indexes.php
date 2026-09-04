<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE INDEX businesses_discovery_visibility_idx ON businesses (status, is_publicly_listed, category_id) WHERE status = 'approved' AND is_publicly_listed = true AND deleted_at IS NULL");
        DB::statement('CREATE INDEX businesses_district_idx ON businesses (district) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX businesses_coordinates_idx ON businesses (latitude, longitude) WHERE latitude IS NOT NULL AND longitude IS NOT NULL AND deleted_at IS NULL');
        DB::statement("CREATE INDEX resources_discovery_idx ON resources (business_id, status, resource_group_id, resource_type) WHERE deleted_at IS NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS businesses_discovery_visibility_idx');
        DB::statement('DROP INDEX IF EXISTS businesses_district_idx');
        DB::statement('DROP INDEX IF EXISTS businesses_coordinates_idx');
        DB::statement('DROP INDEX IF EXISTS resources_discovery_idx');
    }
};
