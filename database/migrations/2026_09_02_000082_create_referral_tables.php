<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('code', 16)->unique();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('code');
        });

        Schema::create('referrals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('referrer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('referred_user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('referral_code_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('registered');
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('rewarded_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('referrer_user_id');
            $table->index('status');
            $table->index('referral_code_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('referral_codes');
    }
};
