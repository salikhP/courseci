package com.courseci.ui.auth

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.safeDrawingPadding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.AlternateEmail
import androidx.compose.material.icons.outlined.Code
import androidx.compose.material.icons.outlined.Person
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import com.courseci.data.fake.DEMO_EMAIL
import com.courseci.data.fake.DEMO_PASSWORD
import com.courseci.ui.components.FormTextField
import com.courseci.ui.components.PasswordTextField
import com.courseci.ui.theme.CourseCiTheme

@Composable
fun LoginScreen(
    onNavigateToRegister: () -> Unit,
    viewModel: LoginViewModel = viewModel(factory = LoginViewModel.Factory),
) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()
    // Form input survives rotation and process death without living in the ViewModel
    var email by rememberSaveable { mutableStateOf("") }
    var password by rememberSaveable { mutableStateOf("") }

    LoginContent(
        email = email,
        password = password,
        uiState = uiState,
        onEmailChange = { email = it; viewModel.onFieldEdited(AuthField.EMAIL) },
        onPasswordChange = { password = it; viewModel.onFieldEdited(AuthField.PASSWORD) },
        onUseDemoAccount = {
            email = DEMO_EMAIL
            password = DEMO_PASSWORD
            viewModel.onFieldEdited(AuthField.EMAIL)
            viewModel.onFieldEdited(AuthField.PASSWORD)
        },
        onLogin = { viewModel.login(email, password) },
        onNavigateToRegister = onNavigateToRegister,
    )
}

@Composable
private fun LoginContent(
    email: String,
    password: String,
    uiState: AuthFormUiState,
    onEmailChange: (String) -> Unit,
    onPasswordChange: (String) -> Unit,
    onUseDemoAccount: () -> Unit,
    onLogin: () -> Unit,
    onNavigateToRegister: () -> Unit,
) {
    AuthLayout(title = "Welcome back", subtitle = "Sign in to follow your assignments and grades") {
        FormTextField(
            value = email,
            onValueChange = onEmailChange,
            label = "Email",
            error = uiState.fieldErrors[AuthField.EMAIL],
            enabled = !uiState.isLoading,
            keyboardType = KeyboardType.Email,
            leadingIcon = Icons.Outlined.AlternateEmail,
        )
        PasswordTextField(
            value = password,
            onValueChange = onPasswordChange,
            label = "Password",
            error = uiState.fieldErrors[AuthField.PASSWORD],
            enabled = !uiState.isLoading,
            onImeAction = onLogin,
        )
        FormError(uiState.formError)
        SubmitButton(text = "Sign in", isLoading = uiState.isLoading, onClick = onLogin)
        OutlinedButton(
            onClick = onUseDemoAccount,
            enabled = !uiState.isLoading,
            modifier = Modifier.fillMaxWidth(),
        ) { Text("Use demo account") }
        TextButton(onClick = onNavigateToRegister, enabled = !uiState.isLoading) {
            Text("New student? Create an account")
        }
    }
}

@Composable
fun RegisterScreen(
    onNavigateBack: () -> Unit,
    viewModel: RegisterViewModel = viewModel(factory = RegisterViewModel.Factory),
) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()
    var name by rememberSaveable { mutableStateOf("") }
    var email by rememberSaveable { mutableStateOf("") }
    var password by rememberSaveable { mutableStateOf("") }
    var confirmation by rememberSaveable { mutableStateOf("") }
    val register = { viewModel.register(name, email, password, confirmation) }

    AuthLayout(title = "Create account", subtitle = "Student accounts only. Teachers are added by the administrator.") {
        FormTextField(
            value = name,
            onValueChange = { name = it; viewModel.onFieldEdited(AuthField.NAME) },
            label = "Full name",
            error = uiState.fieldErrors[AuthField.NAME],
            enabled = !uiState.isLoading,
            leadingIcon = Icons.Outlined.Person,
        )
        FormTextField(
            value = email,
            onValueChange = { email = it; viewModel.onFieldEdited(AuthField.EMAIL) },
            label = "Email",
            error = uiState.fieldErrors[AuthField.EMAIL],
            enabled = !uiState.isLoading,
            keyboardType = KeyboardType.Email,
            leadingIcon = Icons.Outlined.AlternateEmail,
        )
        PasswordTextField(
            value = password,
            onValueChange = { password = it; viewModel.onFieldEdited(AuthField.PASSWORD) },
            label = "Password",
            error = uiState.fieldErrors[AuthField.PASSWORD],
            enabled = !uiState.isLoading,
            imeAction = ImeAction.Next,
        )
        PasswordTextField(
            value = confirmation,
            onValueChange = { confirmation = it; viewModel.onFieldEdited(AuthField.CONFIRMATION) },
            label = "Confirm password",
            error = uiState.fieldErrors[AuthField.CONFIRMATION],
            enabled = !uiState.isLoading,
            onImeAction = register,
        )
        FormError(uiState.formError)
        SubmitButton(text = "Create account", isLoading = uiState.isLoading, onClick = register)
        TextButton(onClick = onNavigateBack, enabled = !uiState.isLoading) {
            Text("Already have an account? Sign in")
        }
    }
}

@Composable
private fun AuthLayout(
    title: String,
    subtitle: String,
    content: @Composable ColumnScope.() -> Unit,
) {
    Surface(Modifier.fillMaxSize(), color = MaterialTheme.colorScheme.background) {
        Box(
            Modifier
                .safeDrawingPadding()
                .imePadding()
                .verticalScroll(rememberScrollState()),
            contentAlignment = Alignment.TopCenter,
        ) {
            Column(
                modifier = Modifier
                    .widthIn(max = 480.dp)
                    .padding(horizontal = 24.dp, vertical = 32.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                AppLogo()
                Text(title, style = MaterialTheme.typography.headlineMedium, modifier = Modifier.padding(top = 8.dp))
                Text(
                    subtitle,
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.padding(bottom = 12.dp),
                )
                content()
            }
        }
    }
}

@Composable
private fun AppLogo() {
    Box(
        Modifier
            .size(72.dp)
            .background(MaterialTheme.colorScheme.primary, RoundedCornerShape(20.dp)),
        contentAlignment = Alignment.Center,
    ) {
        Icon(Icons.Outlined.Code, contentDescription = null, tint = MaterialTheme.colorScheme.onPrimary, modifier = Modifier.size(40.dp))
    }
    Text("CourseCI", style = MaterialTheme.typography.titleLarge, color = MaterialTheme.colorScheme.primary)
}

@Composable
private fun FormError(message: String?) {
    if (message == null) return
    Surface(
        color = MaterialTheme.colorScheme.errorContainer,
        shape = MaterialTheme.shapes.small,
        modifier = Modifier.fillMaxWidth(),
    ) {
        Text(
            message,
            color = MaterialTheme.colorScheme.onErrorContainer,
            style = MaterialTheme.typography.bodyMedium,
            modifier = Modifier.padding(12.dp),
        )
    }
}

@Composable
private fun SubmitButton(text: String, isLoading: Boolean, onClick: () -> Unit) {
    Button(
        onClick = onClick,
        enabled = !isLoading,
        modifier = Modifier
            .fillMaxWidth()
            .padding(top = 4.dp),
    ) {
        if (isLoading) {
            CircularProgressIndicator(Modifier.size(18.dp), strokeWidth = 2.dp, color = MaterialTheme.colorScheme.onPrimary)
        } else {
            Text(text)
        }
    }
}

@Preview(showBackground = true)
@Composable
private fun LoginContentPreview() {
    CourseCiTheme {
        LoginContent(
            email = DEMO_EMAIL,
            password = "",
            uiState = AuthFormUiState(fieldErrors = mapOf(AuthField.PASSWORD to "Enter your password")),
            onEmailChange = {},
            onPasswordChange = {},
            onUseDemoAccount = {},
            onLogin = {},
            onNavigateToRegister = {},
        )
    }
}
