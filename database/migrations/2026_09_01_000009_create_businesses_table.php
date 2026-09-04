<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_id')->constrained('business_categories')->restrictOnDelete();
            $table->foreignUuid('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 32)->default('draft');
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->jsonb('translations')->nullable();
            $table->string('phone', 16)->nullable();
            $table->string('email', 255)->nullable();
            $table->char('country_code', 2)->default('UZ');
            $table->string('region', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('district', 120)->nullable();
            $table->string('address_line', 255)->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->string('timezone', 64)->default('Asia/Tashkent');
            $table->text('cover_image_url')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->foreignUuid('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index('category_id');
            $table->index('city');
            $table->index('deleted_at');
        });

        DB::statement("ALTER TABLE businesses ADD CONSTRAINT businesses_status_check CHECK (status IN ('draft', 'pending_review', 'approved', 'rejected', 'suspended', 'archived'))");
        DB::statement('ALTER TABLE businesses ADD CONSTRAINT businesses_coordinates_check CHECK (
            (latitude IS NULL AND longitude IS NULL) OR
            (latitude IS NOT NULL AND longitude IS NOT NULL AND latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180)
        )');

        DB::statement("CREATE INDEX businesses_approved_city_idx ON businesses (city, status) WHERE status = 'approved' AND deleted_at IS NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS businesses_approved_city_idx');
        Schema::dropIfExists('businesses');
    }
};
