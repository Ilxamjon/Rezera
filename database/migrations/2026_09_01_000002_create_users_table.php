<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('phone', 16)->unique();
            $table->string('email', 255)->nullable()->unique();
            $table->string('password');
            $table->text('avatar_url')->nullable();
            $table->string('locale', 8)->default('ru');
            $table->string('platform_role', 32)->default('user');
            $table->string('status', 32)->default('active');
            $table->timestampTz('phone_verified_at')->nullable();
            $table->timestampTz('email_verified_at')->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index('deleted_at');
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_locale_check CHECK (locale IN ('uz', 'kaa', 'ru'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_platform_role_check CHECK (platform_role IN ('user', 'platform_admin'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active', 'blocked'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
