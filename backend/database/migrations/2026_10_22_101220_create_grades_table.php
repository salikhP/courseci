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
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('submission_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('grader_id')->constrained('users')->restrictOnDelete();
            $table->decimal('score', 8, 2);
            $table->text('feedback')->nullable();
            $table->timestampTz('graded_at');
            $table->timestampsTz();

            $table->unique(['grade_item_id', 'student_id']);
            $table->index(['student_id', 'grade_item_id']);
        });

        DB::statement('ALTER TABLE grades ADD CONSTRAINT grades_score_nonnegative CHECK (score >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
