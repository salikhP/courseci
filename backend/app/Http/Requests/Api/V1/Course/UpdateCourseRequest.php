<?php

namespace App\Http\Requests\Api\V1\Course;

use App\Models\Course;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $course = $this->route('course');

        return $course instanceof Course
            && ($this->user()?->can('update', $course) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = [];

        if ($this->has('title')) {
            $input['title'] = $this->string('title')->trim()->toString();
        }

        if ($this->has('description')) {
            $input['description'] = is_string($this->input('description'))
                ? $this->string('description')->trim()->toString()
                : $this->input('description');
        }

        $this->merge($input);
    }
}
