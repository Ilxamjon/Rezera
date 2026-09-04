<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_favorites', function (Blueprint $table): void {
            $table->index(['business_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('business_favorites', function (Blueprint $table): void {
            $table->dropIndex(['business_id', 'created_at']);
        });
    }
};
