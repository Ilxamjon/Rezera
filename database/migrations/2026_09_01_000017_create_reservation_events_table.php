<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 32);
            $table->text('reason')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['reservation_id', 'created_at']);
        });

        DB::statement("ALTER TABLE reservation_events ADD CONSTRAINT reservation_events_to_status_check CHECK (to_status IN ('pending', 'confirmed', 'rejected', 'cancelled', 'expired', 'checked_in', 'completed', 'no_show'))");
        DB::statement("ALTER TABLE reservation_events ADD CONSTRAINT reservation_events_from_status_check CHECK (from_status IS NULL OR from_status IN ('pending', 'confirmed', 'rejected', 'cancelled', 'expired', 'checked_in', 'completed', 'no_show'))");
        DB::statement("ALTER TABLE reservation_events ADD CONSTRAINT reservation_events_source_check CHECK (source IN ('api_customer', 'api_owner', 'system_job', 'admin'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_events');
    }
};
