<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->nullable()->constrained('businesses')->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->string('discount_type', 32);
            $table->bigInteger('discount_value');
            $table->char('currency', 3)->nullable();
            $table->bigInteger('minimum_amount')->nullable();
            $table->bigInteger('maximum_discount')->nullable();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->unsignedInteger('per_user_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->jsonb('metadata')->nullable();
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index(['business_id', 'is_active']);
            $table->index(['starts_at', 'ends_at']);
            $table->index('code');
        });

        DB::statement("ALTER TABLE promo_codes ADD CONSTRAINT promo_codes_discount_type_check CHECK (discount_type IN ('percentage', 'fixed'))");
        DB::statement('ALTER TABLE promo_codes ADD CONSTRAINT promo_codes_discount_value_check CHECK (discount_value > 0)');
        DB::statement('ALTER TABLE promo_codes ADD CONSTRAINT promo_codes_percentage_check CHECK (discount_type <> \'percentage\' OR discount_value <= 100)');
        DB::statement("CREATE UNIQUE INDEX promo_codes_business_code_unique ON promo_codes (business_id, code) WHERE business_id IS NOT NULL AND deleted_at IS NULL");
        DB::statement("CREATE UNIQUE INDEX promo_codes_platform_code_unique ON promo_codes (code) WHERE business_id IS NULL AND deleted_at IS NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS promo_codes_platform_code_unique');
        DB::statement('DROP INDEX IF EXISTS promo_codes_business_code_unique');
        Schema::dropIfExists('promo_codes');
    }
};
