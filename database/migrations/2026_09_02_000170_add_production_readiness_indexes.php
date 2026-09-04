<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->index('status');
            $table->index('verification_status');
            $table->index('created_by_user_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->index('status');
            $table->index('platform_role');
        });

        Schema::table('reservations', function (Blueprint $table): void {
            $table->index(['business_id', 'payment_status']);
        });

        Schema::table('saved_search_alerts', function (Blueprint $table): void {
            $table->index(['business_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropIndex(['verification_status']);
            $table->dropIndex(['created_by_user_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropIndex(['platform_role']);
        });

        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropIndex(['business_id', 'payment_status']);
        });

        Schema::table('saved_search_alerts', function (Blueprint $table): void {
            $table->dropIndex(['business_id', 'created_at']);
            $table->dropIndex(['status']);
        });
    }
};
