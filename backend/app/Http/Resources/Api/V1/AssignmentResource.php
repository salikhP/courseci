<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Assignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Assignment */
class AssignmentResource extends JsonResource
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
            'course_id' => $this->gradeItem->course_id,
            'grade_item_id' => $this->grade_item_id,
            'title' => $this->gradeItem->title,
            'description' => $this->gradeItem->description,
            'max_score' => $this->gradeItem->max_score,
            'opens_at' => $this->opens_at?->toISOString(),
            'due_at' => $this->due_at?->toISOString(),
            'archived_at' => $this->gradeItem->archived_at?->toISOString(),
            'is_open' => $this->isOpenForSubmissions(),
            'grading_requirements' => Assignment::GRADING_REQUIREMENTS,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
