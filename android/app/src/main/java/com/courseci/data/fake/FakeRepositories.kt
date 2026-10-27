package com.courseci.data.fake

import com.courseci.data.model.Assignment
import com.courseci.data.model.Course
import com.courseci.data.model.Grade
import com.courseci.data.model.GradingStatus
import com.courseci.data.model.Student
import com.courseci.data.model.Submission
import com.courseci.data.repository.AuthException
import com.courseci.data.repository.AuthRepository
import com.courseci.data.repository.CourseRepository
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import java.time.Clock
import kotlin.time.Duration
import kotlin.time.Duration.Companion.milliseconds
import kotlin.time.Duration.Companion.seconds

/** Simulated network latency and grading timings */
data class FakeTimings(
    val networkLatency: Duration = 600.milliseconds,
    val queuedFor: Duration = 2.seconds,
    val runningFor: Duration = 4.seconds,
)

class FakeAuthRepository(
    private val database: MutableStateFlow<FakeDatabase>,
    private val timings: FakeTimings,
) : AuthRepository {

    private val _currentStudent = MutableStateFlow<Student?>(null)
    override val currentStudent: StateFlow<Student?> = _currentStudent.asStateFlow()

    override suspend fun login(email: String, password: String): Result<Student> {
        delay(timings.networkLatency)
        val account = database.value.students.firstOrNull { it.student.email.equals(email.trim(), ignoreCase = true) }
        if (account == null || account.password != password) {
            return Result.failure(AuthException("These credentials do not match our records."))
        }
        _currentStudent.value = account.student
        return Result.success(account.student)
    }

    override suspend fun register(name: String, email: String, password: String): Result<Student> {
        delay(timings.networkLatency)
        val normalizedEmail = email.trim().lowercase()
        var created: Student? = null
        database.update { db ->
            created = null
            if (db.students.any { it.student.email.equals(normalizedEmail, ignoreCase = true) }) return@update db
            val student = Student(id = (db.students.maxOfOrNull { it.student.id } ?: 0) + 1, name.trim(), normalizedEmail)
            created = student
            db.copy(students = db.students + StudentAccount(student, password))
        }
        val student = created ?: return Result.failure(AuthException("The email has already been taken."))
        _currentStudent.value = student
        return Result.success(student)
    }

    override fun logout() {
        _currentStudent.value = null
    }
}

/**
 * Course data served from the in-memory [database]. Grading runs on
 * [applicationScope] so it keeps going when the user leaves the screen
 */
class FakeCourseRepository(
    private val database: MutableStateFlow<FakeDatabase>,
    private val applicationScope: CoroutineScope,
    private val clock: Clock,
    private val timings: FakeTimings,
) : CourseRepository {

    private fun <T> select(selector: (FakeDatabase) -> T): Flow<T> =
        database.map(selector).distinctUntilChanged()

    override fun observeCourses(studentId: Long): Flow<List<Course>> = select { db ->
        val enrolled = db.enrollments[studentId].orEmpty()
        db.courses.filter { it.id in enrolled }
    }

    override fun observeCourse(courseId: Long): Flow<Course?> = select { db ->
        db.courses.firstOrNull { it.id == courseId }
    }

    override fun observeAssignments(courseId: Long): Flow<List<Assignment>> = select { db ->
        db.assignments.filter { it.courseId == courseId }
    }

    override fun observeAssignment(assignmentId: Long): Flow<Assignment?> = select { db ->
        db.assignments.firstOrNull { it.id == assignmentId }
    }

    override fun observeSubmissions(studentId: Long): Flow<List<Submission>> = select { db ->
        db.submissions
            .filter { it.studentId == studentId }
            .sortedWith(compareByDescending<Submission> { it.submittedAt }.thenByDescending { it.id })
    }

    override fun observeGrades(studentId: Long): Flow<List<Grade>> = select { db ->
        db.grades.filter { it.studentId == studentId }
    }

    override suspend fun submit(studentId: Long, assignmentId: Long, repositoryUrl: String): Submission {
        delay(timings.networkLatency)
        lateinit var submission: Submission
        database.update { db ->
            val previousAttempts = db.submissions.count { it.studentId == studentId && it.assignmentId == assignmentId }
            submission = Submission(
                id = (db.submissions.maxOfOrNull { it.id } ?: 0) + 1,
                assignmentId = assignmentId,
                studentId = studentId,
                repositoryUrl = repositoryUrl,
                attemptNumber = previousAttempts + 1,
                status = GradingStatus.QUEUED,
                submittedAt = clock.instant(),
            )
            db.copy(submissions = db.submissions + submission)
        }
        applicationScope.launch { runGrading(submission) }
        return submission
    }

    private suspend fun runGrading(submission: Submission) {
        delay(timings.queuedFor)
        updateSubmission(submission.id) { it.copy(status = GradingStatus.RUNNING) }
        delay(timings.runningFor)
        val outcome = FakeGradingRunner.grade(submission.repositoryUrl)
        updateSubmission(submission.id) { it.copy(status = outcome.status, checks = outcome.checks) }
    }

    private fun updateSubmission(id: Long, transform: (Submission) -> Submission) {
        database.update { db ->
            db.copy(submissions = db.submissions.map { if (it.id == id) transform(it) else it })
        }
    }
}
