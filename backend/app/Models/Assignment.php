<?php

namespace App\Models;

use Database\Factories\AssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['grade_item_id', 'opens_at', 'due_at'])]
class Assignment extends Model
{
    public const array GRADING_REQUIREMENTS = [
        'language' => 'go',
        'checks' => ['go build ./...', 'go vet ./...', 'go test ./...'],
    ];

    /** @use HasFactory<AssignmentFactory> */
    use HasFactory;

    public function gradeItem(): BelongsTo
    {
        return $this->belongsTo(GradeItem::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class, 'grade_item_id', 'grade_item_id');
    }

    public function isOpenForSubmissions(): bool
    {
        $now = now();

        return $this->gradeItem->archived_at === null
            && $this->gradeItem->course->archived_at === null
            && ($this->opens_at === null || $this->opens_at->lessThanOrEqualTo($now))
            && ($this->due_at === null || $this->due_at->greaterThanOrEqualTo($now));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }
}
