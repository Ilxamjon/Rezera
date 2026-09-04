<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignUuid('business_subscription_id')->nullable()->after('reservation_id')->constrained('business_subscriptions')->nullOnDelete();
            $table->index('business_subscription_id');
        });

        DB::statement('ALTER TABLE payments ALTER COLUMN reservation_id DROP NOT NULL');
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_target_check CHECK (reservation_id IS NOT NULL OR business_subscription_id IS NOT NULL)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_target_check');
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('business_subscription_id');
        });
    }
};
