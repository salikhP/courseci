package com.courseci.ui.grades

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

sealed interface GradesUiState {
    data object Loading : GradesUiState

    data class Ready(val courses: List<CourseOverview>) : GradesUiState {
        val earnedScore: Double = courses.sumOf { it.progress.earnedScore }
        val gradedMaxScore: Double = courses.sumOf { it.progress.gradedMaxScore }
        val gradedCount: Int = courses.sumOf { it.progress.gradedCount }
        val totalCount: Int = courses.sumOf { it.progress.totalCount }

        /** Overall average across every graded assignment, or `null` before the first grade */
        val overallPercent: Double? =
            if (gradedMaxScore > 0) earnedScore / gradedMaxScore * 100 else null
    }
}

class GradesViewModel(
    authRepository: AuthRepository,
    courseworkInteractor: CourseworkInteractor,
) : ViewModel() {

    @OptIn(ExperimentalCoroutinesApi::class)
    val uiState: StateFlow<GradesUiState> = authRepository.currentStudent
        .filterNotNull()
        .flatMapLatest { courseworkInteractor.observeCourseOverviews(it.id) }
        .map { GradesUiState.Ready(it) }
        .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5_000), GradesUiState.Loading)

    companion object {
        val Factory = appViewModelFactory { container, _ ->
            GradesViewModel(container.authRepository, container.courseworkInteractor)
        }
    }
}
