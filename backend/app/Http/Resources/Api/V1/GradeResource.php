<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Grade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Grade */
class GradeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'grade_item_id' => $this->grade_item_id,
            'submission_id' => $this->submission_id,
            'score' => $this->score,
            'feedback' => $this->feedback,
            'grader_id' => $this->grader_id,
            'graded_at' => $this->graded_at?->toISOString(),
        ];
    }
}
