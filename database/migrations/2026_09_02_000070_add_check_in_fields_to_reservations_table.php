<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->timestampTz('checked_out_at')->nullable()->after('checked_in_at');
            $table->foreignUuid('checked_in_by_user_id')->nullable()->after('checked_out_at')->constrained('users')->nullOnDelete();
            $table->foreignUuid('checked_out_by_user_id')->nullable()->after('checked_in_by_user_id')->constrained('users')->nullOnDelete();
            $table->string('check_in_method', 32)->nullable()->after('checked_out_by_user_id');
            $table->string('check_out_method', 32)->nullable()->after('check_in_method');
        });

        DB::statement("ALTER TABLE reservations ADD CONSTRAINT reservations_check_in_method_check CHECK (check_in_method IS NULL OR check_in_method IN ('qr', 'staff', 'customer', 'system'))");
        DB::statement("ALTER TABLE reservations ADD CONSTRAINT reservations_check_out_method_check CHECK (check_out_method IS NULL OR check_out_method IN ('qr', 'staff', 'customer', 'system'))");
        DB::statement('CREATE INDEX reservations_checked_in_at_idx ON reservations (business_id, checked_in_at) WHERE checked_in_at IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS reservations_checked_in_at_idx');
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('checked_in_by_user_id');
            $table->dropConstrainedForeignId('checked_out_by_user_id');
            $table->dropColumn(['checked_out_at', 'check_in_method', 'check_out_method']);
        });
    }
};
