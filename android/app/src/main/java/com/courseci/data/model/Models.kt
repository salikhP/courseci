package com.courseci.data.model

import java.time.Instant

/**
 * Domain models mirroring the backend schema (users, courses, assignments,
 * submissions, grading_runs, grades). Field names follow the API so the fake
 * repository can later be replaced by a network-backed one
 */

data class Student(
    val id: Long,
    val name: String,
    val email: String,
)

data class Course(
    val id: Long,
    val title: String,
    val description: String,
    val teacherName: String,
)

data class Assignment(
    val id: Long,
    val courseId: Long,
    val title: String,
    val description: String,
    val maxScore: Double,
    val opensAt: Instant?,
    val dueAt: Instant?,
)

/** Mirrors the backend `GradingStatus` enum */
enum class GradingStatus(val label: String) {
    QUEUED("Queued"),
    RUNNING("Running"),
    PASSED("Passed"),
    FAILED("Failed"),
    TIMED_OUT("Timed out"),
    SYSTEM_ERROR("System error");

    val isFinished: Boolean
        get() = this != QUEUED && this != RUNNING
}

/** One automated check of a grading run, e.g. `go build` or `go test` */
data class CheckResult(
    val name: String,
    val command: String,
    val passed: Boolean,
    val output: String,
)

data class Submission(
    val id: Long,
    val assignmentId: Long,
    val studentId: Long,
    val repositoryUrl: String,
    val attemptNumber: Int,
    val status: GradingStatus,
    val submittedAt: Instant,
    val checks: List<CheckResult> = emptyList(),
)

/** Final grade assigned by the teacher after reviewing a submission */
data class Grade(
    val assignmentId: Long,
    val studentId: Long,
    val score: Double,
    val feedback: String?,
    val gradedAt: Instant,
)
