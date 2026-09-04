<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->foreignUuid('resource_id')->nullable()->after('reservation_id')->constrained()->nullOnDelete();
            $table->index(['business_id', 'rating']);
            $table->index('resource_id');
        });

        DB::statement('ALTER TABLE reviews DROP CONSTRAINT IF EXISTS reviews_status_check');
        DB::statement("ALTER TABLE reviews ADD CONSTRAINT reviews_status_check CHECK (status IN ('published', 'hidden', 'pending', 'rejected'))");

        Schema::table('businesses', function (Blueprint $table): void {
            $table->decimal('rating_average', 4, 2)->nullable()->after('status');
            $table->unsignedInteger('rating_count')->default(0)->after('rating_average');
        });

        Schema::create('review_reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('review_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('reporter_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 32);
            $table->text('description')->nullable();
            $table->string('status', 32)->default('open');
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();

            $table->unique(['review_id', 'reporter_user_id']);
            $table->index(['review_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        DB::statement("ALTER TABLE review_reports ADD CONSTRAINT review_reports_status_check CHECK (status IN ('open', 'resolved', 'dismissed'))");

        $this->backfillRatingAggregates();
    }

    private function backfillRatingAggregates(): void
    {
        DB::statement(<<<'SQL'
            UPDATE businesses
            SET
                rating_average = sub.average,
                rating_count = sub.total
            FROM (
                SELECT
                    business_id,
                    ROUND(AVG(rating)::numeric, 2) AS average,
                    COUNT(*) AS total
                FROM reviews
                WHERE status = 'published' AND deleted_at IS NULL
                GROUP BY business_id
            ) AS sub
            WHERE businesses.id = sub.business_id
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('review_reports');

        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropColumn(['rating_average', 'rating_count']);
        });

        DB::statement('ALTER TABLE reviews DROP CONSTRAINT IF EXISTS reviews_status_check');
        DB::statement("ALTER TABLE reviews ADD CONSTRAINT reviews_status_check CHECK (status IN ('published', 'hidden', 'pending'))");

        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('resource_id');
        });
    }
};
