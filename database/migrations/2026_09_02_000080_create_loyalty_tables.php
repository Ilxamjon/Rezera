<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_programs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->unsignedInteger('earn_rate_points')->default(1);
            $table->unsignedBigInteger('earn_amount')->default(1000);
            $table->unsignedInteger('flat_points_per_reservation')->nullable();
            $table->unsignedBigInteger('minimum_qualifying_amount')->nullable();
            $table->unsignedInteger('max_points_per_transaction')->nullable();
            $table->unsignedInteger('points_expiration_days')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('loyalty_accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->integer('balance')->default(0);
            $table->unsignedBigInteger('lifetime_earned')->default(0);
            $table->unsignedBigInteger('lifetime_redeemed')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['business_id', 'user_id']);
            $table->index('business_id');
            $table->index('user_id');
            $table->index('status');
        });

        Schema::create('loyalty_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('loyalty_account_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('business_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->integer('points');
            $table->integer('balance_after');
            $table->string('source_type')->nullable();
            $table->uuid('source_id')->nullable();
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['loyalty_account_id', 'source_type', 'source_id', 'type'], 'loyalty_tx_idempotency');
            $table->index('loyalty_account_id');
            $table->index('business_id');
            $table->index('user_id');
            $table->index('type');
            $table->index(['source_type', 'source_id']);
            $table->index('expires_at');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('loyalty_accounts');
        Schema::dropIfExists('loyalty_programs');
    }
};
