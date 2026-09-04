<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('notification_id')->constrained('user_notifications')->cascadeOnDelete();
            $table->string('channel', 32);
            $table->string('provider', 32)->nullable();
            $table->string('status', 32);
            $table->string('provider_message_id', 128)->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->string('error_message', 500)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->string('idempotency_key', 128);
            $table->timestampsTz();

            $table->unique('idempotency_key');
            $table->index(['notification_id', 'channel']);
            $table->index('status');
            $table->index('provider_message_id');
        });

        DB::statement("ALTER TABLE notification_deliveries ADD CONSTRAINT notification_deliveries_status_check CHECK (status IN ('pending', 'processing', 'sent', 'delivered', 'failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
