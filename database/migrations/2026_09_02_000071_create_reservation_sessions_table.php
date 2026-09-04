<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('reservation_id')->constrained('reservations')->restrictOnDelete();
            $table->foreignUuid('business_id')->constrained('businesses')->restrictOnDelete();
            $table->foreignUuid('resource_id')->constrained('resources')->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
            $table->string('start_method', 32);
            $table->string('end_method', 32)->nullable();
            $table->foreignUuid('started_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('ended_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32);
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['business_id', 'status']);
            $table->index(['resource_id', 'status']);
            $table->index(['reservation_id', 'status']);
            $table->index('started_at');
            $table->index('ended_at');
        });

        DB::statement("ALTER TABLE reservation_sessions ADD CONSTRAINT reservation_sessions_start_method_check CHECK (start_method IN ('qr', 'staff', 'customer', 'system'))");
        DB::statement("ALTER TABLE reservation_sessions ADD CONSTRAINT reservation_sessions_end_method_check CHECK (end_method IS NULL OR end_method IN ('qr', 'staff', 'customer', 'system'))");
        DB::statement("ALTER TABLE reservation_sessions ADD CONSTRAINT reservation_sessions_status_check CHECK (status IN ('active', 'completed', 'cancelled'))");
        DB::statement("CREATE UNIQUE INDEX reservation_sessions_active_reservation_unique ON reservation_sessions (reservation_id) WHERE status = 'active'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS reservation_sessions_active_reservation_unique');
        Schema::dropIfExists('reservation_sessions');
    }
};
