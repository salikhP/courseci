<?php

namespace App\Http\Requests\Api\V1\Assignment;

use App\Models\Assignment;
use App\Models\Course;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class StoreAssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $course = $this->route('course');

        return $course instanceof Course
            && ($this->user()?->can('create', [Assignment::class, $course]) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'max_score' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999.99'],
            'opens_at' => ['sometimes', 'nullable', 'date'],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'grading_requirements' => ['prohibited'],
        ];
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['opens_at', 'due_at'])) {
                return;
            }

            $assignment = $this->route('assignment');
            $opensAt = $this->exists('opens_at') ? $this->input('opens_at') : $assignment?->opens_at;
            $dueAt = $this->exists('due_at') ? $this->input('due_at') : $assignment?->due_at;

            if ($opensAt !== null && $dueAt !== null && Carbon::parse($dueAt)->lessThanOrEqualTo(Carbon::parse($opensAt))) {
                $validator->errors()->add('due_at', 'The due at field must be after opens at.');
            }
        }];
    }

    /** @return array<string, Carbon|null> */
    public function scheduleAttributes(): array
    {
        $schedule = [];

        foreach ($this->safe()->only(['opens_at', 'due_at']) as $field => $value) {
            $schedule[$field] = $value === null ? null : Carbon::parse($value)->utc();
        }

        return $schedule;
    }

    protected function prepareForValidation(): void
    {
        $input = [];

        foreach (['title', 'description'] as $field) {
            if (is_string($this->input($field))) {
                $input[$field] = $this->string($field)->trim()->toString();
            }
        }

        $this->merge($input);
    }
}
