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
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->string('repository_url', 2048);
            $table->string('commit_sha', 64)->nullable();
            $table->unsignedInteger('attempt_number');
            $table->enum('status', [
                'queued',
                'running',
                'passed',
                'failed',
                'timed_out',
                'system_error',
            ])->default('queued');
            $table->timestampTz('submitted_at')->useCurrent();
            $table->timestampsTz();

            $table->unique(['assignment_id', 'student_id', 'attempt_number']);
            $table->index(['assignment_id', 'status', 'submitted_at', 'id']);
            $table->index(['student_id', 'submitted_at', 'id']);
        });

        DB::statement('ALTER TABLE submissions ADD CONSTRAINT submissions_attempt_number_positive CHECK (attempt_number > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
