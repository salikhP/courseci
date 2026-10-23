<?php

namespace App\Models;

use App\Enums\GradingStatus;
use Database\Factories\GradingRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['submission_id', 'run_number', 'status', 'checks', 'stdout', 'stderr', 'exit_code', 'error_code', 'runner_metadata', 'started_at', 'finished_at'])]
class GradingRun extends Model
{
    /** @use HasFactory<GradingRunFactory> */
    use HasFactory;

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => GradingStatus::class,
            'checks' => 'array',
            'runner_metadata' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
