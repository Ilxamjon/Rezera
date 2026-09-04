<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('title', 150)->nullable();
            $table->text('body')->nullable();
            $table->string('status', 32)->default('published');
            $table->text('business_response')->nullable();
            $table->foreignUuid('business_responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('business_responded_at')->nullable();
            $table->timestampTz('edited_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('hidden_at')->nullable();
            $table->foreignUuid('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('hidden_reason')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->unique('reservation_id');
            $table->index(['business_id', 'status', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('rating');
        });

        DB::statement("ALTER TABLE reviews ADD CONSTRAINT reviews_rating_check CHECK (rating BETWEEN 1 AND 5)");
        DB::statement("ALTER TABLE reviews ADD CONSTRAINT reviews_status_check CHECK (status IN ('published', 'hidden', 'pending'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
