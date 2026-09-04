<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->string('member_role', 32)->default('owner');
            $table->string('status', 32)->default('active');
            $table->timestampsTz();

            $table->unique(['business_id', 'user_id']);
            $table->index('business_id');
            $table->index('user_id');
        });

        DB::statement("ALTER TABLE business_members ADD CONSTRAINT business_members_role_check CHECK (member_role IN ('owner', 'manager', 'staff'))");
        DB::statement("ALTER TABLE business_members ADD CONSTRAINT business_members_status_check CHECK (status IN ('active', 'revoked'))");

        DB::statement("CREATE INDEX business_members_active_user_idx ON business_members (user_id, status) WHERE status = 'active'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS business_members_active_user_idx');
        Schema::dropIfExists('business_members');
    }
};
