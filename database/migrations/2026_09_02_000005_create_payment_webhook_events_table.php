<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider', 32);
            $table->string('event_id', 128);
            $table->foreignUuid('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('event_type', 64);
            $table->jsonb('payload');
            $table->string('signature', 512)->nullable();
            $table->string('status', 32);
            $table->timestampTz('processed_at')->nullable();
            $table->string('error_message', 500)->nullable();
            $table->timestampsTz();

            $table->unique(['provider', 'event_id']);
            $table->index(['payment_id', 'created_at']);
            $table->index('status');
        });

        DB::statement("ALTER TABLE payment_webhook_events ADD CONSTRAINT payment_webhook_events_status_check CHECK (status IN ('pending', 'processed', 'failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
    }
};
