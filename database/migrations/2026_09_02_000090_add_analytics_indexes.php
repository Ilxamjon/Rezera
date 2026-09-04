<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS payments_business_status_paid_at_idx ON payments (business_id, status, paid_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS loyalty_transactions_business_created_idx ON loyalty_transactions (business_id, created_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS reservation_sessions_business_started_idx ON reservation_sessions (business_id, started_at)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS payments_business_status_paid_at_idx');
        DB::statement('DROP INDEX IF EXISTS loyalty_transactions_business_created_idx');
        DB::statement('DROP INDEX IF EXISTS reservation_sessions_business_started_idx');
    }
};
