package com.courseci.domain

import com.courseci.data.model.Course
import com.courseci.data.repository.CourseRepository
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.flatMapLatest
import kotlinx.coroutines.flow.flowOf
import java.time.Clock

/** A course with its assignments resolved for one student */
data class CourseOverview(
    val course: Course,
    val assignments: List<AssignmentOverview>,
    val progress: CourseProgress,
) {
    /** The nearest assignment the student still has to hand in */
    val nextDeadline: AssignmentOverview?
        get() = assignments.firstOrNull { it.state == AssignmentState.DUE_SOON || it.state == AssignmentState.OPEN }

    val toDoCount: Int
        get() = assignments.count { it.state in TO_DO_STATES }
}

val TO_DO_STATES = setOf(AssignmentState.OPEN, AssignmentState.DUE_SOON, AssignmentState.OVERDUE)

/** Joins courses, assignments, submissions and grades into per-course overviews */
class CourseworkInteractor(
    private val courseRepository: CourseRepository,
    private val clock: Clock,
) {
    @OptIn(ExperimentalCoroutinesApi::class)
    fun observeCourseOverviews(studentId: Long): Flow<List<CourseOverview>> =
        courseRepository.observeCourses(studentId).flatMapLatest { courses ->
            if (courses.isEmpty()) return@flatMapLatest flowOf(emptyList())

            val assignmentsPerCourse = combine(courses.map { courseRepository.observeAssignments(it.id) }) { it.toList() }
            combine(
                assignmentsPerCourse,
                courseRepository.observeSubmissions(studentId),
                courseRepository.observeGrades(studentId),
            ) { assignmentLists, submissions, grades ->
                val now = clock.instant()
                courses.zip(assignmentLists) { course, assignments ->
                    val overviews = buildOverviews(assignments, submissions, grades, now)
                    CourseOverview(course, overviews, calculateProgress(overviews))
                }
            }
        }
}
