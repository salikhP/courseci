package com.courseci.data.fake

import com.courseci.data.model.Assignment
import com.courseci.data.model.CheckResult
import com.courseci.data.model.Course
import com.courseci.data.model.Grade
import com.courseci.data.model.GradingStatus
import com.courseci.data.model.Student
import com.courseci.data.model.Submission
import java.time.Clock
import java.time.Duration
import java.time.Instant

/** In-memory snapshot of everything the fake backend knows */
data class FakeDatabase(
    val students: List<StudentAccount>,
    val courses: List<Course>,
    /** studentId -> enrolled course ids */
    val enrollments: Map<Long, Set<Long>>,
    val assignments: List<Assignment>,
    val submissions: List<Submission>,
    val grades: List<Grade>,
)

data class StudentAccount(val student: Student, val password: String)

const val DEMO_EMAIL = "student@courseci.dev"
const val DEMO_PASSWORD = "password"

/** Builds demo data with deadlines which are calculated from the app starts */
fun seedDatabase(clock: Clock): FakeDatabase {
    val now = clock.instant()
    fun daysFromNow(days: Long): Instant = now.plus(Duration.ofDays(days))
    fun hoursFromNow(hours: Long): Instant = now.plus(Duration.ofHours(hours))

    val demo = Student(id = 1, name = "Aruzhan Bekova", email = DEMO_EMAIL)

    val courses = listOf(
        Course(
            id = 1,
            title = "Go Programming Fundamentals",
            description = "Syntax, types, packages, testing and tooling of the Go language.",
            teacherName = "Dr. Nurlan Seitkali",
        ),
        Course(
            id = 2,
            title = "Concurrent Programming in Go",
            description = "Goroutines, channels, synchronization primitives and context cancellation.",
            teacherName = "Dr. Nurlan Seitkali",
        ),
        Course(
            id = 3,
            title = "Algorithms and Data Structures",
            description = "Sorting, searching, graphs and dynamic programming implemented in Go.",
            teacherName = "Prof. Dana Omarova",
        ),
    )

    val assignments = listOf(
        Assignment(1, 1, "Hello, CourseCI", "Create a Go module with a `Greet(name string) string` function and table-driven tests.", 10.0, daysFromNow(-30), daysFromNow(-21)),
        Assignment(2, 1, "Word Counter", "Implement `CountWords(text string) map[string]int` that is case-insensitive and ignores punctuation.", 20.0, daysFromNow(-20), daysFromNow(-10)),
        Assignment(3, 1, "Bank Account", "Model a bank account with deposits, withdrawals and typed errors for insufficient funds.", 20.0, daysFromNow(-7), hoursFromNow(30)),
        Assignment(4, 1, "JSON Config Loader", "Parse a JSON configuration file into structs and validate required fields.", 25.0, daysFromNow(-1), daysFromNow(9)),
        Assignment(5, 1, "Final Project Proposal", "Submit a repository containing a README describing your final project.", 25.0, daysFromNow(10), daysFromNow(24)),
        Assignment(6, 2, "Parallel Sum", "Split a slice across N goroutines and combine partial sums through a channel.", 15.0, daysFromNow(-25), daysFromNow(-15)),
        Assignment(7, 2, "Worker Pool", "Build a bounded worker pool that processes jobs and reports results in order.", 25.0, daysFromNow(-14), daysFromNow(-2)),
        Assignment(8, 2, "Rate Limiter", "Implement a token-bucket rate limiter safe for concurrent use.", 30.0, daysFromNow(-3), daysFromNow(5)),
        Assignment(9, 3, "Binary Search", "Implement iterative and recursive binary search with exhaustive tests.", 10.0, daysFromNow(-18), daysFromNow(-11)),
        Assignment(10, 3, "Graph Traversal", "Implement BFS and DFS over an adjacency list and find connected components.", 20.0, daysFromNow(-5), hoursFromNow(12)),
        Assignment(11, 3, "Shortest Paths", "Implement Dijkstra's algorithm with a binary heap.", 30.0, daysFromNow(3), daysFromNow(17)),
    )

    val passedChecks = listOf(
        CheckResult("Build", "go build ./...", passed = true, output = "ok"),
        CheckResult("Vet", "go vet ./...", passed = true, output = "no issues found"),
        CheckResult("Tests", "go test ./...", passed = true, output = "ok    courseci/solution  0.214s"),
    )
    val failedTestChecks = listOf(
        CheckResult("Build", "go build ./...", passed = true, output = "ok"),
        CheckResult("Vet", "go vet ./...", passed = true, output = "no issues found"),
        CheckResult(
            "Tests", "go test ./...", passed = false,
            output = "--- FAIL: TestCountWords/punctuation (0.00s)\n    words_test.go:31: got 4 words, want 3\nFAIL",
        ),
    )

    val submissions = listOf(
        Submission(1, 1, demo.id, "https://github.com/aruzhan-b/hello-courseci", 1, GradingStatus.PASSED, daysFromNow(-23), passedChecks),
        Submission(2, 2, demo.id, "https://github.com/aruzhan-b/word-counter", 1, GradingStatus.FAILED, daysFromNow(-13), failedTestChecks),
        Submission(3, 2, demo.id, "https://github.com/aruzhan-b/word-counter", 2, GradingStatus.PASSED, daysFromNow(-12), passedChecks),
        Submission(4, 6, demo.id, "https://github.com/aruzhan-b/parallel-sum", 1, GradingStatus.PASSED, daysFromNow(-16), passedChecks),
        Submission(5, 7, demo.id, "https://gitlab.com/aruzhan-b/worker-pool", 1, GradingStatus.PASSED, daysFromNow(-3), passedChecks),
        Submission(6, 9, demo.id, "https://github.com/aruzhan-b/binary-search", 1, GradingStatus.PASSED, daysFromNow(-12), passedChecks),
    )

    val grades = listOf(
        Grade(1, demo.id, 10.0, "Clean table-driven tests. Well done.", daysFromNow(-20)),
        Grade(2, demo.id, 17.0, "Second attempt passes; -3 for the first failing submission.", daysFromNow(-9)),
        Grade(6, demo.id, 14.0, "Correct, but the channel is never closed explicitly.", daysFromNow(-14)),
        Grade(9, demo.id, 8.0, "Recursive version could avoid slicing the input.", daysFromNow(-10)),
    )

    return FakeDatabase(
        students = listOf(StudentAccount(demo, DEMO_PASSWORD)),
        courses = courses,
        enrollments = mapOf(demo.id to setOf(1L, 2L, 3L)),
        assignments = assignments,
        submissions = submissions,
        grades = grades,
    )
}
