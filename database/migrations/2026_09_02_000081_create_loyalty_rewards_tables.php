<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_rewards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('points_cost');
            $table->string('reward_type', 30);
            $table->unsignedBigInteger('value')->nullable();
            $table->string('currency', 3)->nullable();
            $table->unsignedInteger('stock')->nullable();
            $table->unsignedInteger('max_redemptions_per_user')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('business_id');
            $table->index('is_active');
            $table->index('starts_at');
            $table->index('ends_at');
        });

        Schema::create('loyalty_redemptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('loyalty_reward_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('loyalty_account_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('points_spent');
            $table->string('status', 20)->default('redeemed');
            $table->string('redemption_code', 32)->unique();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('business_id');
            $table->index('user_id');
            $table->index('loyalty_reward_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_redemptions');
        Schema::dropIfExists('loyalty_rewards');
    }
};
