<?php

namespace App\Http\Requests\Api\V1\Submission;

use App\Models\Assignment;
use App\Models\Submission;
use App\Rules\PublicRepositoryUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSubmissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $assignment = $this->route('assignment');

        return $assignment instanceof Assignment
            && ($this->user()?->can('create', [Submission::class, $assignment]) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'repository_url' => ['bail', 'required', 'string', 'max:2048', new PublicRepositoryUrl],
            /** @ignoreParam Validated from the Idempotency-Key header. */
            'idempotency_key' => ['required', 'string', 'uuid', 'size:36'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => strtolower((string) $this->header('Idempotency-Key', ''))]);

        if (is_string($this->input('repository_url'))) {
            $this->merge(['repository_url' => $this->string('repository_url')->trim()->toString()]);
        }
    }
}
