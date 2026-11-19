<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\GradeItem;
use App\Models\User;

class AssignmentPolicy
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
    public function view(User $user, Assignment $assignment): bool
    {
        return $this->canViewCourse($user, $assignment->gradeItem->course);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Course|GradeItem $parent): bool
    {
        $course = $parent instanceof Course ? $parent : $parent->course;

        return $this->managesCourse($user, $course)
            && $course->archived_at === null
            && ($parent instanceof Course || $parent->archived_at === null);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Assignment $assignment): bool
    {
        return $this->create($user, $assignment->gradeItem);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Assignment $assignment): bool
    {
        return $this->managesCourse($user, $assignment->gradeItem->course)
            && $assignment->gradeItem->course->archived_at === null;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Assignment $assignment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Assignment $assignment): bool
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
