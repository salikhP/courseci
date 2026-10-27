package com.courseci.ui.course

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import androidx.navigation.toRoute
import com.courseci.data.repository.AuthRepository
import com.courseci.domain.AssignmentOverview
import com.courseci.domain.AssignmentState
import com.courseci.domain.CourseOverview
import com.courseci.domain.CourseworkInteractor
import com.courseci.domain.TO_DO_STATES
import com.courseci.ui.appViewModelFactory
import com.courseci.ui.navigation.CourseDetailRoute
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.filterNotNull
import kotlinx.coroutines.flow.flatMapLatest
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.flow.stateIn
import java.time.Clock
import java.time.Instant

/** Assignment list filters shown as chips on the course screen */
enum class AssignmentFilter(val label: String, private val states: Set<AssignmentState>?) {
    ALL("All", null),
    TO_DO("To do", TO_DO_STATES),
    IN_REVIEW("In review", setOf(AssignmentState.GRADING, AssignmentState.AWAITING_REVIEW)),
    GRADED("Graded", setOf(AssignmentState.GRADED));

    fun matches(overview: AssignmentOverview): Boolean = states == null || overview.state in states
}

sealed interface CourseDetailUiState {
    data object Loading : CourseDetailUiState
    data object NotFound : CourseDetailUiState
    data class Ready(val overview: CourseOverview, val now: Instant) : CourseDetailUiState
}

class CourseDetailViewModel(
    private val courseId: Long,
    authRepository: AuthRepository,
    courseworkInteractor: CourseworkInteractor,
    clock: Clock,
) : ViewModel() {

    @OptIn(ExperimentalCoroutinesApi::class)
    val uiState: StateFlow<CourseDetailUiState> = authRepository.currentStudent
        .filterNotNull()
        .flatMapLatest { student -> courseworkInteractor.observeCourseOverviews(student.id) }
        .map { courses ->
            val overview = courses.firstOrNull { it.course.id == courseId }
            if (overview == null) CourseDetailUiState.NotFound else CourseDetailUiState.Ready(overview, clock.instant())
        }
        .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5_000), CourseDetailUiState.Loading)

    companion object {
        val Factory = appViewModelFactory { container, savedStateHandle ->
            CourseDetailViewModel(
                courseId = savedStateHandle.toRoute<CourseDetailRoute>().courseId,
                authRepository = container.authRepository,
                courseworkInteractor = container.courseworkInteractor,
                clock = container.clock,
            )
        }
    }
}
