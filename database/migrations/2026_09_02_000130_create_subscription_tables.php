<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->json('translations')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedBigInteger('monthly_price')->default(0);
            $table->unsignedBigInteger('yearly_price')->default(0);
            $table->char('currency', 3)->default('UZS');
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->json('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['is_active', 'is_public', 'sort_order']);
        });

        Schema::create('plan_entitlements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->string('feature_code', 64);
            $table->string('value_type', 32);
            $table->text('value');
            $table->json('metadata')->nullable();
            $table->timestampsTz();

            $table->unique(['plan_id', 'feature_code']);
            $table->index('feature_code');
        });

        DB::statement("ALTER TABLE plan_entitlements ADD CONSTRAINT plan_entitlements_value_type_check CHECK (value_type IN ('boolean', 'integer', 'string', 'decimal', 'json'))");

        Schema::create('business_subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignUuid('plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->string('status', 32);
            $table->string('billing_interval', 16);
            $table->string('provider', 32)->nullable();
            $table->string('provider_subscription_id', 128)->nullable();
            $table->string('provider_customer_id', 128)->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('trial_ends_at')->nullable();
            $table->timestampTz('current_period_start')->nullable();
            $table->timestampTz('current_period_end')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('cancel_at_period_end')->nullable();
            $table->timestampTz('ended_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->foreignUuid('pending_plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['business_id', 'status']);
            $table->index(['plan_id', 'status']);
            $table->index('current_period_end');
        });

        DB::statement("ALTER TABLE business_subscriptions ADD CONSTRAINT business_subscriptions_status_check CHECK (status IN ('trialing', 'active', 'past_due', 'paused', 'cancelled', 'expired'))");
        DB::statement("ALTER TABLE business_subscriptions ADD CONSTRAINT business_subscriptions_billing_interval_check CHECK (billing_interval IN ('monthly', 'yearly'))");
        DB::statement("CREATE UNIQUE INDEX business_subscriptions_one_effective_per_business_idx ON business_subscriptions (business_id) WHERE status IN ('trialing', 'active', 'past_due', 'paused')");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS business_subscriptions_one_effective_per_business_idx');
        Schema::dropIfExists('business_subscriptions');
        Schema::dropIfExists('plan_entitlements');
        Schema::dropIfExists('subscription_plans');
    }
};
