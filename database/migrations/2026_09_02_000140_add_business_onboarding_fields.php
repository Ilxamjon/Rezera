<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->string('onboarding_status', 32)->default('in_progress')->after('verification_note');
            $table->timestampTz('onboarding_completed_at')->nullable()->after('onboarding_status');
            $table->index('onboarding_status');
        });

        DB::statement('ALTER TABLE businesses DROP CONSTRAINT IF EXISTS businesses_verification_status_check');
        DB::statement("ALTER TABLE businesses ADD CONSTRAINT businesses_verification_status_check CHECK (verification_status IN ('unverified', 'pending', 'verified', 'rejected'))");
        DB::statement("ALTER TABLE businesses ADD CONSTRAINT businesses_onboarding_status_check CHECK (onboarding_status IN ('not_started', 'in_progress', 'completed'))");

        DB::table('businesses')
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->update([
                'verification_status' => 'verified',
                'onboarding_status' => 'completed',
                'onboarding_completed_at' => now(),
            ]);

        DB::table('businesses')
            ->where('status', '!=', 'approved')
            ->where('verification_status', 'pending')
            ->update(['verification_status' => 'unverified']);

        DB::statement("ALTER TABLE businesses ALTER COLUMN verification_status SET DEFAULT 'unverified'");
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropIndex(['onboarding_status']);
            $table->dropColumn(['onboarding_status', 'onboarding_completed_at']);
        });

        DB::statement('ALTER TABLE businesses DROP CONSTRAINT IF EXISTS businesses_onboarding_status_check');
        DB::statement('ALTER TABLE businesses DROP CONSTRAINT IF EXISTS businesses_verification_status_check');
        DB::statement("ALTER TABLE businesses ADD CONSTRAINT businesses_verification_status_check CHECK (verification_status IN ('pending', 'verified', 'rejected'))");
    }
};
