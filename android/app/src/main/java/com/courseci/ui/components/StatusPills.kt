package com.courseci.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.CheckCircle
import androidx.compose.material.icons.outlined.ErrorOutline
import androidx.compose.material.icons.outlined.HourglassTop
import androidx.compose.material.icons.outlined.Schedule
import androidx.compose.material.icons.outlined.Timer
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
import com.courseci.data.model.GradingStatus
import com.courseci.domain.AssignmentState
import com.courseci.ui.theme.CourseCiTheme

/** Small rounded label used for every status in the app */
@Composable
fun StatusPill(
    text: String,
    containerColor: Color,
    contentColor: Color,
    modifier: Modifier = Modifier,
    icon: ImageVector? = null,
    showProgress: Boolean = false,
) {
    Row(
        modifier = modifier
            .background(containerColor, CircleShape)
            .padding(horizontal = 10.dp, vertical = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(4.dp),
    ) {
        when {
            showProgress -> CircularProgressIndicator(
                modifier = Modifier.size(12.dp),
                color = contentColor,
                strokeWidth = 2.dp,
            )

            icon != null -> Icon(icon, contentDescription = null, tint = contentColor, modifier = Modifier.size(14.dp))
        }
        Text(text, style = MaterialTheme.typography.labelMedium, color = contentColor)
    }
}

@Composable
fun GradingStatusPill(status: GradingStatus, modifier: Modifier = Modifier) {
    val colors = MaterialTheme.colorScheme
    val statusColors = CourseCiTheme.statusColors
    when (status) {
        GradingStatus.QUEUED -> StatusPill(
            status.label, colors.secondaryContainer, colors.onSecondaryContainer, modifier, Icons.Outlined.Schedule,
        )

        GradingStatus.RUNNING -> StatusPill(
            status.label, colors.primaryContainer, colors.onPrimaryContainer, modifier, showProgress = true,
        )

        GradingStatus.PASSED -> StatusPill(
            status.label, statusColors.successContainer, statusColors.onSuccessContainer, modifier, Icons.Outlined.CheckCircle,
        )

        GradingStatus.FAILED, GradingStatus.SYSTEM_ERROR -> StatusPill(
            status.label, colors.errorContainer, colors.onErrorContainer, modifier, Icons.Outlined.ErrorOutline,
        )

        GradingStatus.TIMED_OUT -> StatusPill(
            status.label, statusColors.warningContainer, statusColors.onWarningContainer, modifier, Icons.Outlined.Timer,
        )
    }
}

@Composable
fun AssignmentStatePill(state: AssignmentState, modifier: Modifier = Modifier) {
    val colors = MaterialTheme.colorScheme
    val statusColors = CourseCiTheme.statusColors
    val (container, content) = when (state) {
        AssignmentState.UPCOMING -> colors.surfaceContainerHigh to colors.onSurfaceVariant
        AssignmentState.OPEN -> colors.secondaryContainer to colors.onSecondaryContainer
        AssignmentState.DUE_SOON -> statusColors.warningContainer to statusColors.onWarningContainer
        AssignmentState.OVERDUE -> colors.errorContainer to colors.onErrorContainer
        AssignmentState.GRADING -> colors.primaryContainer to colors.onPrimaryContainer
        AssignmentState.AWAITING_REVIEW -> colors.tertiaryContainer to colors.onTertiaryContainer
        AssignmentState.GRADED -> statusColors.successContainer to statusColors.onSuccessContainer
    }
    StatusPill(
        text = state.label,
        containerColor = container,
        contentColor = content,
        modifier = modifier,
        icon = if (state == AssignmentState.AWAITING_REVIEW) Icons.Outlined.HourglassTop else null,
        showProgress = state == AssignmentState.GRADING,
    )
}
