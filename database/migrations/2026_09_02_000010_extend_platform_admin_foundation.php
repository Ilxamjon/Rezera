<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_platform_role_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_platform_role_check CHECK (platform_role IN ('user', 'platform_admin', 'super_admin', 'admin', 'support'))");

        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active', 'suspended', 'blocked'))");

        Schema::table('businesses', function (Blueprint $table): void {
            $table->boolean('is_publicly_listed')->default(true)->after('status');
            $table->string('verification_status', 32)->default('pending')->after('is_publicly_listed');
            $table->text('verification_note')->nullable()->after('verification_status');
            $table->timestampTz('status_changed_at')->nullable()->after('reviewed_by_user_id');
            $table->string('status_change_reason', 500)->nullable()->after('status_changed_at');
        });

        DB::statement("ALTER TABLE businesses ADD CONSTRAINT businesses_verification_status_check CHECK (verification_status IN ('pending', 'verified', 'rejected'))");

        Schema::table('business_categories', function (Blueprint $table): void {
            $table->jsonb('description')->nullable()->after('name');
            $table->string('image_url', 500)->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('business_categories', function (Blueprint $table): void {
            $table->dropColumn(['description', 'image_url']);
        });

        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropColumn([
                'is_publicly_listed',
                'verification_status',
                'verification_note',
                'status_changed_at',
                'status_change_reason',
            ]);
        });

        DB::statement('ALTER TABLE businesses DROP CONSTRAINT IF EXISTS businesses_verification_status_check');

        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active', 'blocked'))");

        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_platform_role_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_platform_role_check CHECK (platform_role IN ('user', 'platform_admin'))");
    }
};
