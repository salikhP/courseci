<?php

namespace App\Http\Requests\Api\V1\Assignment;

use App\Models\Assignment;
use Illuminate\Validation\ValidationException;

class UpdateAssignmentRequest extends StoreAssignmentRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $assignment = $this->route('assignment');

        return $assignment instanceof Assignment
            && ($this->user()?->can('update', $assignment) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        array_unshift($rules['title'], 'sometimes');
        array_unshift($rules['max_score'], 'sometimes');

        return $rules;
    }

    /** Validate mutable state again while the assignment is locked for update. */
    public function validateChanges(Assignment $assignment): void
    {
        $errors = [];
        $opensAt = $this->exists('opens_at') ? $this->date('opens_at') : $assignment->opens_at;
        $dueAt = $this->exists('due_at') ? $this->date('due_at') : $assignment->due_at;

        if ($opensAt !== null && $dueAt !== null && $dueAt->lessThanOrEqualTo($opensAt)) {
            $errors['due_at'] = 'The due at field must be after opens at.';
        }

        if ($assignment->submissions()->exists()) {
            if ($this->exists('max_score') && (float) $this->input('max_score') !== (float) $assignment->gradeItem->max_score) {
                $errors['max_score'] = 'The maximum score cannot be changed after a submission exists.';
            }

            $sameOpening = $opensAt === null
                ? $assignment->opens_at === null
                : $assignment->opens_at !== null && $opensAt->equalTo($assignment->opens_at);

            if (! $sameOpening) {
                $errors['opens_at'] = 'The opening date cannot be changed after a submission exists.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
