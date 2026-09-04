<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name', 120);
            $table->integer('sort_order')->default(0);
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index('business_id');
        });

        DB::statement('CREATE UNIQUE INDEX resource_groups_business_name_unique ON resource_groups (business_id, name) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS resource_groups_business_name_unique');
        Schema::dropIfExists('resource_groups');
    }
};
