package com.courseci.ui

import androidx.lifecycle.ViewModel
import com.courseci.data.model.Student
import com.courseci.data.repository.AuthRepository
import kotlinx.coroutines.flow.StateFlow

/** App-level session state: drives auth gating in the nav graph and logout */
class SessionViewModel(private val authRepository: AuthRepository) : ViewModel() {

    val currentStudent: StateFlow<Student?> = authRepository.currentStudent

    fun logout() = authRepository.logout()

    companion object {
        val Factory = appViewModelFactory { container, _ -> SessionViewModel(container.authRepository) }
    }
}
