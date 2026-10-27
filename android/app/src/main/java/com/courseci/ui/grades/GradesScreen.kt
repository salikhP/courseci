package com.courseci.ui.grades

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.KeyboardArrowRight
import androidx.compose.material.icons.automirrored.outlined.Grading
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import com.courseci.domain.AssignmentOverview
import com.courseci.domain.CourseOverview
import com.courseci.ui.components.EmptyState
import com.courseci.ui.components.LoadingContent
import com.courseci.ui.components.LogoutAction
import com.courseci.ui.components.ScoreProgress
import com.courseci.ui.format.formatPercent
import com.courseci.ui.format.formatScore

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun GradesScreen(
    onCourseClick: (courseId: Long) -> Unit,
    onAssignmentClick: (assignmentId: Long) -> Unit,
    onLogout: () -> Unit,
    viewModel: GradesViewModel = viewModel(factory = GradesViewModel.Factory),
) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()

    Scaffold(
        topBar = {
            TopAppBar(title = { Text("Grades") }, actions = { LogoutAction(onLogout) })
        },
    ) { padding ->
        when (val state = uiState) {
            GradesUiState.Loading -> LoadingContent(Modifier.padding(padding))

            is GradesUiState.Ready -> if (state.courses.isEmpty()) {
                EmptyState(
                    icon = Icons.AutoMirrored.Outlined.Grading,
                    title = "No grades yet",
                    message = "Grades appear here once you are enrolled and your work is reviewed.",
                    modifier = Modifier.padding(padding),
                )
            } else {
                LazyColumn(
                    contentPadding = PaddingValues(16.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp),
                    modifier = Modifier.padding(padding),
                ) {
                    item { SemesterSummary(state) }
                    items(state.courses, key = { it.course.id }) { overview ->
                        CourseGradesCard(
                            overview = overview,
                            onCourseClick = { onCourseClick(overview.course.id) },
                            onAssignmentClick = onAssignmentClick,
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun SemesterSummary(state: GradesUiState.Ready) {
    Card(
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.primary,
            contentColor = MaterialTheme.colorScheme.onPrimary,
        ),
    ) {
        Column(Modifier.fillMaxWidth().padding(20.dp)) {
            Text("Semester average", style = MaterialTheme.typography.labelLarge)
            Text(
                state.overallPercent?.formatPercent() ?: "—",
                style = MaterialTheme.typography.displaySmall,
            )
            Text(
                "${state.gradedCount} of ${state.totalCount} assignments graded · " +
                    "${state.earnedScore.formatScore()} / ${state.gradedMaxScore.formatScore()} pts",
                style = MaterialTheme.typography.bodyMedium,
            )
        }
    }
}

@Composable
private fun CourseGradesCard(
    overview: CourseOverview,
    onCourseClick: () -> Unit,
    onAssignmentClick: (Long) -> Unit,
) {
    val progress = overview.progress
    val graded = overview.assignments.filter { it.grade != null }
    Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceContainerLow)) {
        Column {
            Row(
                Modifier
                    .fillMaxWidth()
                    .clickable(onClick = onCourseClick)
                    .padding(16.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Column(Modifier.weight(1f)) {
                    Text(overview.course.title, style = MaterialTheme.typography.titleMedium)
                    Text(
                        progress.averagePercent?.let { "Average ${it.formatPercent()}" } ?: "No grades yet",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                Icon(Icons.AutoMirrored.Outlined.KeyboardArrowRight, contentDescription = "Open course")
            }
            ScoreProgress(
                fraction = progress.semesterFraction,
                startLabel = "Semester progress",
                endLabel = "${progress.earnedScore.formatScore()} / ${progress.totalMaxScore.formatScore()} pts",
                modifier = Modifier.padding(horizontal = 16.dp),
            )
            if (graded.isNotEmpty()) {
                HorizontalDivider(Modifier.padding(top = 16.dp))
                graded.forEach { GradeRow(it, onClick = { onAssignmentClick(it.assignment.id) }) }
            } else {
                Text(
                    "Nothing graded yet",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.padding(16.dp),
                )
            }
        }
    }
}

@Composable
private fun GradeRow(overview: AssignmentOverview, onClick: () -> Unit) {
    val grade = overview.grade ?: return
    Row(
        Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick)
            .padding(horizontal = 16.dp, vertical = 10.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(Modifier.weight(1f)) {
            Text(overview.assignment.title, style = MaterialTheme.typography.bodyMedium)
            grade.feedback?.let {
                Text(it, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant, maxLines = 1)
            }
        }
        Text(
            "${grade.score.formatScore()} / ${overview.assignment.maxScore.formatScore()}",
            style = MaterialTheme.typography.titleSmall,
            modifier = Modifier.padding(start = 12.dp),
        )
    }
}
