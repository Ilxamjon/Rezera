<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_members', function (Blueprint $table): void {
            $table->string('job_title', 120)->nullable()->after('member_role');
            $table->foreignUuid('invited_by_user_id')->nullable()->after('job_title')->constrained('users')->nullOnDelete();
            $table->timestampTz('joined_at')->nullable()->after('invited_by_user_id');
        });

        Schema::create('business_member_invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('phone', 20);
            $table->foreignUuid('invited_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('invited_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('member_role', 32);
            $table->string('job_title', 120)->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestampTz('expires_at');
            $table->timestampTz('responded_at')->nullable();
            $table->timestampsTz();

            $table->index(['business_id', 'status']);
            $table->index(['phone', 'status']);
            $table->index('invited_user_id');
        });

        DB::statement("ALTER TABLE business_member_invitations ADD CONSTRAINT business_member_invitations_role_check CHECK (member_role IN ('owner', 'manager', 'staff'))");
        DB::statement("ALTER TABLE business_member_invitations ADD CONSTRAINT business_member_invitations_status_check CHECK (status IN ('pending', 'accepted', 'declined', 'revoked', 'expired'))");
        DB::statement("CREATE UNIQUE INDEX business_member_invitations_pending_phone_idx ON business_member_invitations (business_id, phone) WHERE status = 'pending'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS business_member_invitations_pending_phone_idx');

        Schema::dropIfExists('business_member_invitations');

        Schema::table('business_members', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('invited_by_user_id');
            $table->dropColumn(['job_title', 'joined_at']);
        });
    }
};
