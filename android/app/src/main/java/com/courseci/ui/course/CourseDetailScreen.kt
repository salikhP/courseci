package com.courseci.ui.course

import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.outlined.FilterListOff
import androidx.compose.material.icons.outlined.SearchOff
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import com.courseci.domain.AssignmentOverview
import com.courseci.domain.CourseOverview
import com.courseci.ui.components.AssignmentCard
import com.courseci.ui.components.EmptyState
import com.courseci.ui.components.LoadingContent
import com.courseci.ui.components.ScoreProgress
import com.courseci.ui.format.formatPercent
import com.courseci.ui.format.formatScore

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun CourseDetailScreen(
    onAssignmentClick: (assignmentId: Long) -> Unit,
    onNavigateUp: () -> Unit,
    viewModel: CourseDetailViewModel = viewModel(factory = CourseDetailViewModel.Factory),
) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()
    // Pure UI state: which chip is selected. Saved across rotation; the ViewModel doesn't need it
    var filter by rememberSaveable { mutableStateOf(AssignmentFilter.ALL) }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    val title = (uiState as? CourseDetailUiState.Ready)?.overview?.course?.title.orEmpty()
                    Text(title, maxLines = 1, overflow = TextOverflow.Ellipsis)
                },
                navigationIcon = {
                    IconButton(onClick = onNavigateUp) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back")
                    }
                },
            )
        },
    ) { padding ->
        when (val state = uiState) {
            CourseDetailUiState.Loading -> LoadingContent(Modifier.padding(padding))

            CourseDetailUiState.NotFound -> EmptyState(
                icon = Icons.Outlined.SearchOff,
                title = "Course not found",
                message = "You may no longer be enrolled in this course.",
                modifier = Modifier.padding(padding),
            )

            is CourseDetailUiState.Ready -> {
                // Recomputed only when the assignments or the selected filter change
                val visible = remember(state.overview.assignments, filter) {
                    state.overview.assignments.filter(filter::matches)
                }
                LazyColumn(
                    contentPadding = PaddingValues(16.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp),
                    modifier = Modifier.padding(padding),
                ) {
                    item { CourseHeader(state.overview) }
                    item {
                        FilterChips(
                            selected = filter,
                            assignments = state.overview.assignments,
                            onSelect = { filter = it },
                        )
                    }
                    if (visible.isEmpty()) {
                        item {
                            EmptyState(
                                icon = Icons.Outlined.FilterListOff,
                                title = "Nothing here",
                                message = "No assignments match “${filter.label}”.",
                            )
                        }
                    }
                    items(visible, key = { it.assignment.id }) { overview ->
                        AssignmentCard(overview, state.now, onClick = { onAssignmentClick(overview.assignment.id) })
                    }
                }
            }
        }
    }
}

@Composable
private fun CourseHeader(overview: CourseOverview) {
    val progress = overview.progress
    Card(
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.primaryContainer,
            contentColor = MaterialTheme.colorScheme.onPrimaryContainer,
        ),
    ) {
        Column(Modifier.fillMaxWidth().padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Text(overview.course.description, style = MaterialTheme.typography.bodyMedium)
            Text("Teacher: ${overview.course.teacherName}", style = MaterialTheme.typography.labelLarge)
            ScoreProgress(
                fraction = progress.semesterFraction,
                startLabel = progress.averagePercent?.let { "Average ${it.formatPercent()}" } ?: "No grades yet",
                endLabel = "${progress.earnedScore.formatScore()} / ${progress.totalMaxScore.formatScore()} pts",
                modifier = Modifier.padding(top = 4.dp),
                trackColor = MaterialTheme.colorScheme.surface,
            )
        }
    }
}

/** Stateless: the selected filter is hoisted to the screen */
@Composable
private fun FilterChips(
    selected: AssignmentFilter,
    assignments: List<AssignmentOverview>,
    onSelect: (AssignmentFilter) -> Unit,
) {
    Row(
        Modifier.horizontalScroll(rememberScrollState()),
        horizontalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        AssignmentFilter.entries.forEach { filter ->
            val count = assignments.count(filter::matches)
            FilterChip(
                selected = filter == selected,
                onClick = { onSelect(filter) },
                label = { Text("${filter.label} ($count)") },
            )
        }
    }
}
