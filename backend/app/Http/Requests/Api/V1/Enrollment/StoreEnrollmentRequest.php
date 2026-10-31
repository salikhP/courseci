<?php

namespace App\Http\Requests\Api\V1\Enrollment;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use LogicException;

class StoreEnrollmentRequest extends FormRequest
{
    private ?User $student = null;

    public function authorize(): bool
    {
        $course = $this->route('course');

        return $course instanceof Course
            && ($this->user()?->can('create', [Enrollment::class, $course]) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('email')) {
                return;
            }

            $this->student = User::query()
                ->where('email', $this->string('email')->toString())
                ->first();

            if ($this->student?->role !== UserRole::Student) {
                $validator->errors()->add('email', 'No student account was found for this email address.');

                return;
            }

            $course = $this->route('course');

            if ($course instanceof Course && $course->enrollments()->whereBelongsTo($this->student, 'student')->exists()) {
                $validator->errors()->add('email', 'This student is already enrolled in the course.');
            }
        }];
    }

    public function student(): User
    {
        return $this->student ?? throw new LogicException('The enrollment request has not been validated.');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => $this->string('email')->trim()->lower()->toString(),
        ]);
    }
}
