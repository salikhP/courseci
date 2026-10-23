<?php

namespace App\Models;

use App\Enums\GradingStatus;
use Database\Factories\SubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['assignment_id', 'student_id', 'repository_url', 'commit_sha', 'attempt_number', 'status', 'submitted_at'])]
class Submission extends Model
{
    /** @use HasFactory<SubmissionFactory> */
    use HasFactory;

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function gradingRuns(): HasMany
    {
        return $this->hasMany(GradingRun::class);
    }

    public function grade(): HasOne
    {
        return $this->hasOne(Grade::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => GradingStatus::class,
            'submitted_at' => 'datetime',
        ];
    }
}
