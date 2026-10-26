<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Submission;
use App\Models\User;

class SubmissionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Submission $submission): bool
    {
        if ($user->role === UserRole::Student) {
            return $submission->student_id === $user->id;
        }

        return $this->managesCourse($user, $submission->assignment->gradeItem->course);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Assignment $assignment): bool
    {
        if ($user->role !== UserRole::Student) {
            return false;
        }

        return $assignment->gradeItem->course
            ->enrollments()
            ->where('student_id', $user->id)
            ->exists();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Submission $submission): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Submission $submission): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Submission $submission): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Submission $submission): bool
    {
        return false;
    }

    private function managesCourse(User $user, Course $course): bool
    {
        return $user->role === UserRole::Teacher && $course->teacher_id === $user->id;
    }
}
