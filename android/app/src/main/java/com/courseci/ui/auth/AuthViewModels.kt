package com.courseci.ui.auth

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.courseci.data.repository.AuthRepository
import com.courseci.domain.CredentialsValidator
import com.courseci.ui.appViewModelFactory
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

/** Field-level errors and request state. Input values themselves are hoisted UI state */
data class AuthFormUiState(
    val fieldErrors: Map<AuthField, String> = emptyMap(),
    val formError: String? = null,
    val isLoading: Boolean = false,
)

enum class AuthField { NAME, EMAIL, PASSWORD, CONFIRMATION }

/** Shared request handling for the login and registration forms */
abstract class AuthFormViewModel : ViewModel() {
    private val _uiState = MutableStateFlow(AuthFormUiState())
    val uiState: StateFlow<AuthFormUiState> = _uiState.asStateFlow()

    /** Clears the error of a field as soon as the user edits it */
    fun onFieldEdited(field: AuthField) {
        _uiState.update { it.copy(fieldErrors = it.fieldErrors - field, formError = null) }
    }

    protected fun submit(errors: Map<AuthField, String?>, request: suspend () -> Result<*>) {
        if (_uiState.value.isLoading) return
        val presentErrors = errors.filterValues { it != null }.mapValues { it.value!! }
        if (presentErrors.isNotEmpty()) {
            _uiState.update { it.copy(fieldErrors = presentErrors, formError = null) }
            return
        }
        _uiState.update { AuthFormUiState(isLoading = true) }
        viewModelScope.launch {
            val result = request()
            // On success the session changes and the nav graph leaves the auth screens
            _uiState.update { AuthFormUiState(formError = result.exceptionOrNull()?.message) }
        }
    }
}

class LoginViewModel(private val authRepository: AuthRepository) : AuthFormViewModel() {

    fun login(email: String, password: String) = submit(
        errors = mapOf(
            AuthField.EMAIL to CredentialsValidator.validateEmail(email),
            AuthField.PASSWORD to CredentialsValidator.validateLoginPassword(password),
        ),
    ) { authRepository.login(email, password) }

    companion object {
        val Factory = appViewModelFactory { container, _ -> LoginViewModel(container.authRepository) }
    }
}

class RegisterViewModel(private val authRepository: AuthRepository) : AuthFormViewModel() {

    fun register(name: String, email: String, password: String, confirmation: String) = submit(
        errors = mapOf(
            AuthField.NAME to CredentialsValidator.validateName(name),
            AuthField.EMAIL to CredentialsValidator.validateEmail(email),
            AuthField.PASSWORD to CredentialsValidator.validateNewPassword(password),
            AuthField.CONFIRMATION to CredentialsValidator.validateConfirmation(password, confirmation),
        ),
    ) { authRepository.register(name, email, password) }

    companion object {
        val Factory = appViewModelFactory { container, _ -> RegisterViewModel(container.authRepository) }
    }
}
