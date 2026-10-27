package com.courseci.ui.courses

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.courseci.data.repository.AuthRepository
import com.courseci.domain.CourseOverview
import com.courseci.domain.CourseworkInteractor
import com.courseci.ui.appViewModelFactory
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.filterNotNull
import kotlinx.coroutines.flow.flatMapLatest
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.flow.stateIn
import java.time.Clock
import java.time.Instant

sealed interface CoursesUiState {
    data object Loading : CoursesUiState

    data class Ready(
        val studentName: String,
        val courses: List<CourseOverview>,
        val now: Instant,
    ) : CoursesUiState {
        val totalToDo: Int get() = courses.sumOf { it.toDoCount }
    }
}

class CoursesViewModel(
    authRepository: AuthRepository,
    courseworkInteractor: CourseworkInteractor,
    clock: Clock,
) : ViewModel() {

    @OptIn(ExperimentalCoroutinesApi::class)
    val uiState: StateFlow<CoursesUiState> = authRepository.currentStudent
        .filterNotNull()
        .flatMapLatest { student ->
            courseworkInteractor.observeCourseOverviews(student.id)
                .map { courses -> CoursesUiState.Ready(student.name, courses, clock.instant()) }
        }
        .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5_000), CoursesUiState.Loading)

    companion object {
        val Factory = appViewModelFactory { container, _ ->
            CoursesViewModel(container.authRepository, container.courseworkInteractor, container.clock)
        }
    }
}
