package com.courseci.ui.components

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.Logout
import androidx.compose.material.icons.outlined.Event
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Badge
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.courseci.domain.AssignmentOverview
import com.courseci.domain.CourseOverview
import com.courseci.ui.format.dueLabel
import com.courseci.ui.format.formatScore
import java.time.Instant

@Composable
fun CourseCard(
    overview: CourseOverview,
    now: Instant,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val progress = overview.progress
    Card(
        onClick = onClick,
        modifier = modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceContainerLow),
    ) {
        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Row(verticalAlignment = Alignment.Top) {
                Column(Modifier.weight(1f)) {
                    Text(overview.course.title, style = MaterialTheme.typography.titleMedium)
                    Text(
                        overview.course.teacherName,
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                if (overview.toDoCount > 0) {
                    Badge(containerColor = MaterialTheme.colorScheme.primary) {
                        Text("${overview.toDoCount} to do", modifier = Modifier.padding(horizontal = 4.dp))
                    }
                }
            }
            overview.nextDeadline?.let { next ->
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    Icon(Icons.Outlined.Event, contentDescription = null, modifier = Modifier.size(16.dp), tint = MaterialTheme.colorScheme.primary)
                    Text(
                        "${next.assignment.title} · ${dueLabel(next.assignment.dueAt, now)}",
                        style = MaterialTheme.typography.bodyMedium,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
            }
            ScoreProgress(
                fraction = progress.semesterFraction,
                startLabel = "${progress.gradedCount} of ${progress.totalCount} graded",
                endLabel = "${progress.earnedScore.formatScore()} / ${progress.totalMaxScore.formatScore()} pts",
                modifier = Modifier.padding(top = 4.dp),
            )
        }
    }
}

@Composable
fun AssignmentCard(
    overview: AssignmentOverview,
    now: Instant,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val assignment = overview.assignment
    Card(
        onClick = onClick,
        modifier = modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceContainerLow),
    ) {
        Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                Text(assignment.title, style = MaterialTheme.typography.titleSmall)
                Text(
                    dueLabel(assignment.dueAt, now),
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                AssignmentStatePill(overview.state, Modifier.padding(top = 4.dp))
            }
            val score = overview.grade?.score?.formatScore() ?: "–"
            Column(horizontalAlignment = Alignment.End) {
                Text(score, style = MaterialTheme.typography.titleLarge)
                Text(
                    "/ ${assignment.maxScore.formatScore()}",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
    }
}

/** Top-bar logout action that asks for confirmation first */
@Composable
fun LogoutAction(onLogout: () -> Unit) {
    var showDialog by rememberSaveable { mutableStateOf(false) }
    IconButton(onClick = { showDialog = true }) {
        Icon(Icons.AutoMirrored.Outlined.Logout, contentDescription = "Sign out")
    }
    if (showDialog) {
        AlertDialog(
            onDismissRequest = { showDialog = false },
            title = { Text("Sign out?") },
            text = { Text("Grading of queued submissions continues on the server.") },
            confirmButton = {
                TextButton(onClick = { showDialog = false; onLogout() }) { Text("Sign out") }
            },
            dismissButton = {
                TextButton(onClick = { showDialog = false }) { Text("Cancel") }
            },
        )
    }
}
