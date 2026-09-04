<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 64)->unique();
            $table->jsonb('name');
            $table->string('icon', 64)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index(['is_active', 'sort_order']);
        });

        DB::statement("ALTER TABLE business_categories ADD CONSTRAINT business_categories_name_is_object CHECK (jsonb_typeof(name) = 'object')");
    }

    public function down(): void
    {
        Schema::dropIfExists('business_categories');
    }
};
