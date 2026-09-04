<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 64);
            $table->string('channel', 32)->default('database');
            $table->string('title', 255);
            $table->text('body');
            $table->jsonb('data')->nullable();
            $table->string('status', 32)->default('sent');
            $table->string('idempotency_key', 128);
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'read_at']);
            $table->index('type');
        });

        DB::statement("ALTER TABLE user_notifications ADD CONSTRAINT user_notifications_status_check CHECK (status IN ('pending', 'sent', 'failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
