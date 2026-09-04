<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_verifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('status', 32);
            $table->foreignUuid('submitted_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('submitted_at');
            $table->foreignUuid('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('admin_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['business_id', 'status']);
            $table->index(['status', 'submitted_at']);
            $table->index('reviewed_by_user_id');
        });

        DB::statement("ALTER TABLE business_verifications ADD CONSTRAINT business_verifications_status_check CHECK (status IN ('pending', 'approved', 'rejected', 'cancelled'))");
        DB::statement("CREATE UNIQUE INDEX business_verifications_one_pending_per_business_idx ON business_verifications (business_id) WHERE status = 'pending'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS business_verifications_one_pending_per_business_idx');
        Schema::dropIfExists('business_verifications');
    }
};
