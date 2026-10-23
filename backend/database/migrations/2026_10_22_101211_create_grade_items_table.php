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
        Schema::create('grade_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('max_score', 8, 2);
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();

            $table->index(['course_id', 'archived_at', 'id']);
        });

        DB::statement('ALTER TABLE grade_items ADD CONSTRAINT grade_items_max_score_positive CHECK (max_score > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_items');
    }
};
