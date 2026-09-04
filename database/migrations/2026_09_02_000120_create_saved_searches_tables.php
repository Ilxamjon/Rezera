<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_saved_searches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('search_query', 120)->nullable();
            $table->foreignUuid('category_id')->nullable()->constrained('business_categories')->nullOnDelete();
            $table->string('city', 120)->nullable();
            $table->string('district', 120)->nullable();
            $table->string('region', 120)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('radius_km', 8, 2)->nullable();
            $table->foreignUuid('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->foreignUuid('resource_category_id')->nullable()->constrained('resource_groups')->nullOnDelete();
            $table->foreignUuid('resource_id')->nullable()->constrained('resources')->nullOnDelete();
            $table->string('resource_type', 32)->nullable();
            $table->unsignedInteger('min_price')->nullable();
            $table->unsignedInteger('max_price')->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('date_mode', 32);
            $table->date('specific_date')->nullable();
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->json('days_of_week')->nullable();
            $table->string('start_time', 5)->nullable();
            $table->string('end_time', 5)->nullable();
            $table->boolean('availability_required')->default(false);
            $table->boolean('alert_enabled')->default(false);
            $table->string('alert_channel', 32)->default('in_app');
            $table->string('alert_frequency', 32)->default('instant');
            $table->timestampTz('last_checked_at')->nullable();
            $table->timestampTz('last_notified_at')->nullable();
            $table->timestampsTz();

            $table->index('user_id');
            $table->index(['user_id', 'alert_enabled']);
            $table->index('business_id');
            $table->index('resource_id');
            $table->index('last_checked_at');
        });

        DB::statement("ALTER TABLE user_saved_searches ADD CONSTRAINT user_saved_searches_date_mode_check CHECK (date_mode IN ('specific_date', 'next_available', 'recurring', 'date_range'))");
        DB::statement("ALTER TABLE user_saved_searches ADD CONSTRAINT user_saved_searches_alert_frequency_check CHECK (alert_frequency IN ('instant', 'daily', 'weekly'))");
        DB::statement("ALTER TABLE user_saved_searches ADD CONSTRAINT user_saved_searches_alert_channel_check CHECK (alert_channel IN ('in_app', 'push'))");

        Schema::create('saved_search_alerts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('saved_search_id')->constrained('user_saved_searches')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignUuid('resource_id')->nullable()->constrained('resources')->nullOnDelete();
            $table->timestampTz('start_at');
            $table->timestampTz('end_at');
            $table->string('match_hash', 64);
            $table->foreignUuid('notification_id')->nullable()->constrained('user_notifications')->nullOnDelete();
            $table->string('status', 32)->default('sent');
            $table->timestampTz('notified_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['saved_search_id', 'match_hash']);
            $table->index(['user_id', 'created_at']);
            $table->index(['saved_search_id', 'created_at']);
        });

        DB::statement("ALTER TABLE saved_search_alerts ADD CONSTRAINT saved_search_alerts_status_check CHECK (status IN ('sent', 'failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_search_alerts');
        Schema::dropIfExists('user_saved_searches');
    }
};
