<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\GradingRun;
use App\Models\User;

class GradingRunPolicy
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
    public function view(User $user, GradingRun $gradingRun): bool
    {
        $submission = $gradingRun->submission;

        if ($user->role === UserRole::Student) {
            return $submission->student_id === $user->id;
        }

        return $user->role === UserRole::Teacher
            && $submission->assignment->gradeItem->course->teacher_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, GradingRun $gradingRun): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, GradingRun $gradingRun): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, GradingRun $gradingRun): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, GradingRun $gradingRun): bool
    {
        return false;
    }
}
