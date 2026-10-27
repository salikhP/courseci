package com.courseci.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Shapes
import androidx.compose.material3.Typography
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.Immutable
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.unit.dp

private val LightColors = lightColorScheme(
    primary = Color(0xFF3F51B5),
    onPrimary = Color.White,
    primaryContainer = Color(0xFFDDE1FF),
    onPrimaryContainer = Color(0xFF001257),
    secondary = Color(0xFF5B5D72),
    secondaryContainer = Color(0xFFE0E0F9),
    onSecondaryContainer = Color(0xFF181A2C),
    tertiary = Color(0xFF00696E),
    tertiaryContainer = Color(0xFF6FF6FE),
    background = Color(0xFFFBF8FF),
    surface = Color(0xFFFBF8FF),
    surfaceContainerLow = Color(0xFFF5F2FA),
    surfaceContainer = Color(0xFFEFEDF4),
    surfaceContainerHigh = Color(0xFFE9E7EF),
    error = Color(0xFFBA1A1A),
    errorContainer = Color(0xFFFFDAD6),
)

private val DarkColors = darkColorScheme(
    primary = Color(0xFFB9C3FF),
    onPrimary = Color(0xFF08218A),
    primaryContainer = Color(0xFF2A3BA0),
    onPrimaryContainer = Color(0xFFDDE1FF),
    secondary = Color(0xFFC4C5DD),
    secondaryContainer = Color(0xFF434559),
    onSecondaryContainer = Color(0xFFE0E0F9),
    tertiary = Color(0xFF4CD9E2),
    tertiaryContainer = Color(0xFF004F53),
    background = Color(0xFF121318),
    surface = Color(0xFF121318),
    surfaceContainerLow = Color(0xFF1A1B21),
    surfaceContainer = Color(0xFF1F1F25),
    surfaceContainerHigh = Color(0xFF292A2F),
    error = Color(0xFFFFB4AB),
    errorContainer = Color(0xFF93000A),
)

/** Semantic colors for grading outcomes that Material's scheme does not cover */
@Immutable
data class StatusColors(
    val success: Color,
    val onSuccess: Color,
    val successContainer: Color,
    val onSuccessContainer: Color,
    val warning: Color,
    val warningContainer: Color,
    val onWarningContainer: Color,
)

private val LightStatusColors = StatusColors(
    success = Color(0xFF1B6D2F),
    onSuccess = Color.White,
    successContainer = Color(0xFFA3F5A8),
    onSuccessContainer = Color(0xFF002107),
    warning = Color(0xFF7C5800),
    warningContainer = Color(0xFFFFDEA6),
    onWarningContainer = Color(0xFF271900),
)

private val DarkStatusColors = StatusColors(
    success = Color(0xFF88D98E),
    onSuccess = Color(0xFF003911),
    successContainer = Color(0xFF00531C),
    onSuccessContainer = Color(0xFFA3F5A8),
    warning = Color(0xFFF7BD48),
    warningContainer = Color(0xFF5E4200),
    onWarningContainer = Color(0xFFFFDEA6),
)

val LocalStatusColors = staticCompositionLocalOf { LightStatusColors }

val MonospaceFamily = FontFamily.Monospace

private val AppShapes = Shapes(
    small = RoundedCornerShape(8.dp),
    medium = RoundedCornerShape(16.dp),
    large = RoundedCornerShape(24.dp),
)

@Composable
fun CourseCiTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit,
) {
    CompositionLocalProvider(
        LocalStatusColors provides if (darkTheme) DarkStatusColors else LightStatusColors,
    ) {
        MaterialTheme(
            colorScheme = if (darkTheme) DarkColors else LightColors,
            typography = Typography(),
            shapes = AppShapes,
            content = content,
        )
    }
}

/** Shortcut for `LocalStatusColors.current`, mirroring `MaterialTheme.colorScheme` */
object CourseCiTheme {
    val statusColors: StatusColors
        @Composable get() = LocalStatusColors.current
}
