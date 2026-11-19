<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->uuid('idempotency_key')->nullable();
            $table->unique(['assignment_id', 'student_id', 'idempotency_key'], 'submissions_idempotency_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropUnique('submissions_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
