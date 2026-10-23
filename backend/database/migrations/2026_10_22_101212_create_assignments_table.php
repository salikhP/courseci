<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_item_id')->unique()->constrained()->restrictOnDelete();
            $table->timestampTz('opens_at')->nullable();
            $table->timestampTz('due_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE assignments ADD CONSTRAINT assignments_valid_schedule CHECK (opens_at IS NULL OR due_at IS NULL OR due_at > opens_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
