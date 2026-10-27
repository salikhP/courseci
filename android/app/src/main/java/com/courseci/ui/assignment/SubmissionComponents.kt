package com.courseci.ui.assignment

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.animateColorAsState
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Cancel
import androidx.compose.material.icons.outlined.CheckCircle
import androidx.compose.material.icons.outlined.ExpandLess
import androidx.compose.material.icons.outlined.ExpandMore
import androidx.compose.material.icons.outlined.Link
import androidx.compose.material.icons.automirrored.outlined.Send
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.courseci.data.model.CheckResult
import com.courseci.data.model.GradingStatus
import com.courseci.data.model.Submission
import com.courseci.domain.SUPPORTED_GIT_HOSTS
import com.courseci.ui.components.FormTextField
import com.courseci.ui.components.GradingStatusPill
import com.courseci.ui.format.formatDateTime
import com.courseci.ui.format.formatRelative
import com.courseci.ui.theme.CourseCiTheme
import com.courseci.ui.theme.MonospaceFamily
import java.time.Instant

/** Stateless submit form: the URL, error and loading flag are all hoisted */
@Composable
fun SubmitRepositoryForm(
    url: String,
    onUrlChange: (String) -> Unit,
    error: String?,
    isSubmitting: Boolean,
    isResubmission: Boolean,
    onSubmit: () -> Unit,
    modifier: Modifier = Modifier,
) {
    Card(
        modifier = modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceContainerLow),
    ) {
        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Text(
                if (isResubmission) "Submit a new attempt" else "Submit your solution",
                style = MaterialTheme.typography.titleMedium,
            )
            Text(
                "Public repository on ${SUPPORTED_GIT_HOSTS.joinToString()}. It will be built and tested automatically.",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            FormTextField(
                value = url,
                onValueChange = onUrlChange,
                label = "Repository URL",
                error = error,
                enabled = !isSubmitting,
                keyboardType = KeyboardType.Uri,
                imeAction = ImeAction.Send,
                onImeAction = onSubmit,
                leadingIcon = Icons.Outlined.Link,
            )
            Button(
                onClick = onSubmit,
                enabled = !isSubmitting && url.isNotBlank(),
                modifier = Modifier.align(Alignment.End),
            ) {
                if (isSubmitting) {
                    CircularProgressIndicator(Modifier.size(18.dp), strokeWidth = 2.dp, color = MaterialTheme.colorScheme.onPrimary)
                } else {
                    Icon(Icons.AutoMirrored.Outlined.Send, contentDescription = null, modifier = Modifier.size(18.dp))
                    Text("Submit", modifier = Modifier.padding(start = 8.dp))
                }
            }
        }
    }
}

/** The latest attempt: pipeline progress plus per-check feedback */
@Composable
fun SubmissionResultCard(submission: Submission, now: Instant, modifier: Modifier = Modifier) {
    Card(
        modifier = modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceContainerLow),
    ) {
        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Column(Modifier.weight(1f)) {
                    Text("Attempt #${submission.attemptNumber}", style = MaterialTheme.typography.titleMedium)
                    Text(
                        "Submitted ${submission.submittedAt.formatRelative(now)}",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                GradingStatusPill(submission.status)
            }
            Text(
                submission.repositoryUrl,
                style = MaterialTheme.typography.bodySmall,
                fontFamily = MonospaceFamily,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            GradingPipeline(submission.status)
            if (submission.checks.isNotEmpty()) {
                HorizontalDivider()
                submission.checks.forEach { check ->
                    CheckResultItem(check, key = "${submission.id}-${check.name}")
                }
            }
        }
    }
}

/** Queued → Running → Result, highlighting the current step */
@Composable
private fun GradingPipeline(status: GradingStatus) {
    val step = when (status) {
        GradingStatus.QUEUED -> 0
        GradingStatus.RUNNING -> 1
        else -> 2
    }
    val labels = listOf("Queued", "Running", if (status.isFinished) status.label else "Result")
    Row(verticalAlignment = Alignment.CenterVertically) {
        labels.forEachIndexed { index, label ->
            val active = index <= step
            val color by animateColorAsState(
                if (active) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.outlineVariant,
                label = "pipelineStep",
            )
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                Box(Modifier.size(12.dp).background(color, CircleShape))
                Text(
                    label,
                    style = MaterialTheme.typography.labelSmall,
                    color = if (active) MaterialTheme.colorScheme.onSurface else MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.padding(top = 4.dp),
                )
            }
            if (index < labels.lastIndex) {
                Box(
                    Modifier
                        .weight(1f)
                        .padding(horizontal = 4.dp, vertical = 0.dp)
                        .padding(bottom = 18.dp)
                        .height(2.dp)
                        .background(if (index < step) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.outlineVariant),
                )
            }
        }
    }
}

/** One automated check; tapping it reveals the raw command output */
@Composable
private fun CheckResultItem(check: CheckResult, key: String) {
    var expanded by rememberSaveable(key) { mutableStateOf(!check.passed) }
    val statusColors = CourseCiTheme.statusColors
    Column {
        Row(
            Modifier
                .fillMaxWidth()
                .clickable { expanded = !expanded }
                .padding(vertical = 6.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            Icon(
                if (check.passed) Icons.Outlined.CheckCircle else Icons.Outlined.Cancel,
                contentDescription = if (check.passed) "Passed" else "Failed",
                tint = if (check.passed) statusColors.success else MaterialTheme.colorScheme.error,
                modifier = Modifier.size(20.dp),
            )
            Column(Modifier.weight(1f)) {
                Text(check.name, style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.Medium)
                Text(
                    check.command,
                    style = MaterialTheme.typography.bodySmall,
                    fontFamily = MonospaceFamily,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            Icon(
                if (expanded) Icons.Outlined.ExpandLess else Icons.Outlined.ExpandMore,
                contentDescription = if (expanded) "Hide output" else "Show output",
            )
        }
        AnimatedVisibility(expanded) {
            Surface(
                color = MaterialTheme.colorScheme.surfaceContainerHigh,
                shape = MaterialTheme.shapes.small,
                modifier = Modifier.fillMaxWidth(),
            ) {
                Text(
                    check.output,
                    style = MaterialTheme.typography.bodySmall,
                    fontFamily = MonospaceFamily,
                    softWrap = false,
                    modifier = Modifier
                        .horizontalScroll(rememberScrollState())
                        .padding(12.dp),
                )
            }
        }
    }
}

@Composable
fun PreviousAttemptRow(submission: Submission, modifier: Modifier = Modifier) {
    Row(
        modifier.fillMaxWidth().padding(vertical = 8.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(Modifier.weight(1f)) {
            Text("Attempt #${submission.attemptNumber}", style = MaterialTheme.typography.bodyMedium)
            Text(
                submission.submittedAt.formatDateTime(),
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        GradingStatusPill(submission.status)
    }
}
