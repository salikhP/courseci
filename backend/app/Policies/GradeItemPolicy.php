<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\GradeItem;
use App\Models\User;

class GradeItemPolicy
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
    public function view(User $user, GradeItem $gradeItem): bool
    {
        return $this->canViewCourse($user, $gradeItem->course);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Course $course): bool
    {
        return $this->managesCourse($user, $course);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, GradeItem $gradeItem): bool
    {
        return $this->managesCourse($user, $gradeItem->course);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, GradeItem $gradeItem): bool
    {
        return $this->update($user, $gradeItem);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, GradeItem $gradeItem): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, GradeItem $gradeItem): bool
    {
        return false;
    }

    private function canViewCourse(User $user, Course $course): bool
    {
        if ($this->managesCourse($user, $course)) {
            return true;
        }

        return $user->role === UserRole::Student
            && $course->enrollments()->where('student_id', $user->id)->exists();
    }

    private function managesCourse(User $user, Course $course): bool
    {
        return $user->role === UserRole::Teacher && $course->teacher_id === $user->id;
    }
}
