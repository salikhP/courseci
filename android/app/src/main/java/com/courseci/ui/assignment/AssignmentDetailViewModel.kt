package com.courseci.ui.assignment

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import androidx.navigation.toRoute
import com.courseci.data.model.Assignment
import com.courseci.data.model.Course
import com.courseci.data.model.Grade
import com.courseci.data.model.Submission
import com.courseci.data.repository.AuthRepository
import com.courseci.data.repository.CourseRepository
import com.courseci.domain.AssignmentState
import com.courseci.domain.RepositoryUrlValidator
import com.courseci.domain.canSubmit
import com.courseci.domain.resolveAssignmentState
import com.courseci.ui.appViewModelFactory
import com.courseci.ui.navigation.AssignmentDetailRoute
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.filterNotNull
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.flatMapLatest
import kotlinx.coroutines.flow.flowOf
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import java.time.Clock
import java.time.Instant

/** Transient state of the submit form. The URL text itself is hoisted UI state */
data class SubmitFormState(
    val urlError: String? = null,
    val isSubmitting: Boolean = false,
    /** Incremented after each successful submission so the screen clears its input */
    val resetKey: Int = 0,
)

sealed interface AssignmentDetailUiState {
    data object Loading : AssignmentDetailUiState
    data object NotFound : AssignmentDetailUiState

    data class Ready(
        val assignment: Assignment,
        val course: Course?,
        val state: AssignmentState,
        /** Newest first */
        val attempts: List<Submission>,
        val grade: Grade?,
        val canSubmit: Boolean,
        val form: SubmitFormState,
        val now: Instant,
    ) : AssignmentDetailUiState {
        val latestAttempt: Submission? get() = attempts.firstOrNull()
    }
}

class AssignmentDetailViewModel(
    private val assignmentId: Long,
    private val authRepository: AuthRepository,
    private val courseRepository: CourseRepository,
    private val clock: Clock,
) : ViewModel() {

    private val form = MutableStateFlow(SubmitFormState())

    @OptIn(ExperimentalCoroutinesApi::class)
    private val coursework = authRepository.currentStudent.filterNotNull().flatMapLatest { student ->
        val assignment = courseRepository.observeAssignment(assignmentId)
        val course = assignment.flatMapLatest { it?.let { courseRepository.observeCourse(it.courseId) } ?: flowOf(null) }
        val attempts = courseRepository.observeSubmissions(student.id).map { all -> all.filter { it.assignmentId == assignmentId }.sortedByDescending { it.attemptNumber } }
        val grade = courseRepository.observeGrades(student.id).map { all -> all.firstOrNull { it.assignmentId == assignmentId } }
        combine(assignment, course, attempts, grade, ::Coursework)
    }

    val uiState: StateFlow<AssignmentDetailUiState> = combine(coursework, form) { data, form ->
        val assignment = data.assignment ?: return@combine AssignmentDetailUiState.NotFound
        val now = clock.instant()
        val latest = data.attempts.firstOrNull()
        AssignmentDetailUiState.Ready(
            assignment = assignment,
            course = data.course,
            state = resolveAssignmentState(assignment, latest, data.grade, now),
            attempts = data.attempts,
            grade = data.grade,
            canSubmit = canSubmit(assignment, latest, data.grade, now),
            form = form,
            now = now,
        )
    }.stateIn(viewModelScope, SharingStarted.WhileSubscribed(5_000), AssignmentDetailUiState.Loading)

    fun onUrlChanged() {
        if (form.value.urlError != null) form.update { it.copy(urlError = null) }
    }

    fun submit(repositoryUrl: String) {
        val ready = uiState.value as? AssignmentDetailUiState.Ready ?: return
        if (form.value.isSubmitting || !ready.canSubmit) return

        val error = RepositoryUrlValidator.validate(repositoryUrl)
        if (error != null) {
            form.update { it.copy(urlError = error) }
            return
        }

        form.update { it.copy(isSubmitting = true, urlError = null) }
        viewModelScope.launch {
            val student = authRepository.currentStudent.filterNotNull().first()
            courseRepository.submit(student.id, assignmentId, RepositoryUrlValidator.normalize(repositoryUrl))
            form.update { SubmitFormState(resetKey = it.resetKey + 1) }
        }
    }

    private data class Coursework(
        val assignment: Assignment?,
        val course: Course?,
        val attempts: List<Submission>,
        val grade: Grade?,
    )

    companion object {
        val Factory = appViewModelFactory { container, savedStateHandle ->
            AssignmentDetailViewModel(
                assignmentId = savedStateHandle.toRoute<AssignmentDetailRoute>().assignmentId,
                authRepository = container.authRepository,
                courseRepository = container.courseRepository,
                clock = container.clock,
            )
        }
    }
}
