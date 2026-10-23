<?php

namespace App\Models;

use Database\Factories\GradeItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['course_id', 'title', 'description', 'max_score', 'archived_at'])]
class GradeItem extends Model
{
    /** @use HasFactory<GradeItemFactory> */
    use HasFactory;

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function assignment(): HasOne
    {
        return $this->hasOne(Assignment::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'max_score' => 'decimal:2',
            'archived_at' => 'datetime',
        ];
    }
}
