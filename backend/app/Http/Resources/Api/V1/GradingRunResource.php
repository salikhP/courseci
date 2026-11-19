<?php

namespace App\Http\Resources\Api\V1;

use App\Models\GradingRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GradingRun */
class GradingRunResource extends JsonResource
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
            'run_number' => $this->run_number,
            'status' => $this->status->value,
            'checks' => $this->checks,
            'stdout' => $this->stdout,
            'stderr' => $this->stderr,
            'exit_code' => $this->exit_code,
            'error_code' => $this->error_code,
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
        ];
    }
}
