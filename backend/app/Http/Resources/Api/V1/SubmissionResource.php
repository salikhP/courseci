<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Submission */
class SubmissionResource extends JsonResource
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
            'assignment_id' => $this->assignment_id,
            'student' => new StudentSummaryResource($this->whenLoaded('student')),
            'repository_url' => $this->repository_url,
            'commit_sha' => $this->commit_sha,
            'attempt_number' => $this->attempt_number,
            'status' => $this->status->value,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'grading_result' => new GradingRunResource($this->whenLoaded('latestGradingRun')),
            'grading_runs' => GradingRunResource::collection($this->whenLoaded('gradingRuns')),
            'grade' => new GradeResource($this->whenLoaded('grade')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
