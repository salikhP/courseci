<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Grade;
use App\Models\GradeItem;
use App\Models\User;

class GradePolicy
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
    public function view(User $user, Grade $grade): bool
    {
        if ($user->role === UserRole::Student) {
            return $grade->student_id === $user->id;
        }

        return $user->role === UserRole::Teacher
            && $grade->gradeItem->course->teacher_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, GradeItem $gradeItem): bool
    {
        return $user->role === UserRole::Teacher
            && $gradeItem->course->teacher_id === $user->id;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Grade $grade): bool
    {
        return $user->role === UserRole::Teacher
            && $grade->gradeItem->course->teacher_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Grade $grade): bool
    {
        return $this->update($user, $grade);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Grade $grade): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Grade $grade): bool
    {
        return false;
    }
}
