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
        Schema::create('grading_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('run_number');
            $table->enum('status', [
                'queued',
                'running',
                'passed',
                'failed',
                'timed_out',
                'system_error',
            ])->default('queued');
            $table->jsonb('checks')->nullable();
            $table->text('stdout')->nullable();
            $table->text('stderr')->nullable();
            $table->integer('exit_code')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->jsonb('runner_metadata')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();

            $table->unique(['submission_id', 'run_number']);
        });

        DB::statement('ALTER TABLE grading_runs ADD CONSTRAINT grading_runs_run_number_positive CHECK (run_number > 0)');
        DB::statement('ALTER TABLE grading_runs ADD CONSTRAINT grading_runs_stdout_bounded CHECK (stdout IS NULL OR octet_length(stdout) <= 65535)');
        DB::statement('ALTER TABLE grading_runs ADD CONSTRAINT grading_runs_stderr_bounded CHECK (stderr IS NULL OR octet_length(stderr) <= 65535)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grading_runs');
    }
};
