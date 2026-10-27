package com.courseci.domain

import com.courseci.data.model.Assignment
import com.courseci.data.model.Grade
import com.courseci.data.model.Submission
import java.time.Duration
import java.time.Instant

/** Where an assignment stands for the student, derived from deadlines, submissions and grades */
enum class AssignmentState(val label: String) {
    UPCOMING("Opens soon"),
    OPEN("Open"),
    DUE_SOON("Due soon"),
    OVERDUE("Missing"),
    GRADING("Grading"),
    AWAITING_REVIEW("Awaiting review"),
    GRADED("Graded"),
}

val DUE_SOON_WINDOW: Duration = Duration.ofHours(48)

fun resolveAssignmentState(
    assignment: Assignment,
    latestSubmission: Submission?,
    grade: Grade?,
    now: Instant,
): AssignmentState = when {
    grade != null -> AssignmentState.GRADED
    latestSubmission != null && !latestSubmission.status.isFinished -> AssignmentState.GRADING
    latestSubmission != null -> AssignmentState.AWAITING_REVIEW
    assignment.opensAt != null && now < assignment.opensAt -> AssignmentState.UPCOMING
    assignment.dueAt != null && now > assignment.dueAt -> AssignmentState.OVERDUE
    assignment.dueAt != null && Duration.between(now, assignment.dueAt) <= DUE_SOON_WINDOW -> AssignmentState.DUE_SOON
    else -> AssignmentState.OPEN
}

/** Whether the student may (re)submit right now. Resubmission is allowed until the deadline */
fun canSubmit(assignment: Assignment, latestSubmission: Submission?, grade: Grade?, now: Instant): Boolean {
    if (grade != null) return false
    if (latestSubmission != null && !latestSubmission.status.isFinished) return false
    if (assignment.opensAt != null && now < assignment.opensAt) return false
    if (assignment.dueAt != null && now > assignment.dueAt) return false
    return true
}

/** An assignment together with everything the UI needs to display it */
data class AssignmentOverview(
    val assignment: Assignment,
    val state: AssignmentState,
    val latestSubmission: Submission?,
    val grade: Grade?,
)

fun buildOverviews(
    assignments: List<Assignment>,
    submissions: List<Submission>,
    grades: List<Grade>,
    now: Instant,
): List<AssignmentOverview> {
    val latestByAssignment = submissions
        .groupBy { it.assignmentId }
        .mapValues { (_, attempts) -> attempts.maxBy { it.attemptNumber } }
    val gradeByAssignment = grades.associateBy { it.assignmentId }

    return assignments
        .map { assignment ->
            val latest = latestByAssignment[assignment.id]
            val grade = gradeByAssignment[assignment.id]
            AssignmentOverview(assignment, resolveAssignmentState(assignment, latest, grade, now), latest, grade)
        }
        .sortedWith(compareBy(nullsLast<Instant>()) { it.assignment.dueAt })
}

/** Semester progress for one course */
data class CourseProgress(
    val earnedScore: Double,
    val gradedMaxScore: Double,
    val totalMaxScore: Double,
    val gradedCount: Int,
    val totalCount: Int,
) {
    /** Average over graded work only, or `null` before the first grade */
    val averagePercent: Double?
        get() = if (gradedMaxScore > 0) earnedScore / gradedMaxScore * 100 else null

    /** Share of the whole course's points already earned */
    val semesterFraction: Float
        get() = if (totalMaxScore > 0) (earnedScore / totalMaxScore).toFloat() else 0f
}

fun calculateProgress(overviews: List<AssignmentOverview>): CourseProgress {
    val graded = overviews.filter { it.grade != null }
    return CourseProgress(
        earnedScore = graded.sumOf { it.grade!!.score },
        gradedMaxScore = graded.sumOf { it.assignment.maxScore },
        totalMaxScore = overviews.sumOf { it.assignment.maxScore },
        gradedCount = graded.size,
        totalCount = overviews.size,
    )
}
