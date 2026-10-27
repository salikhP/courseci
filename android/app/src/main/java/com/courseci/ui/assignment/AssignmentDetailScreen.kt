package com.courseci.ui.assignment

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.outlined.Info
import androidx.compose.material.icons.outlined.SearchOff
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import com.courseci.data.model.Grade
import com.courseci.domain.AssignmentState
import com.courseci.ui.components.AssignmentStatePill
import com.courseci.ui.components.EmptyState
import com.courseci.ui.components.LoadingContent
import com.courseci.ui.format.dueLabel
import com.courseci.ui.format.formatDateTime
import com.courseci.ui.format.formatScore
import com.courseci.ui.theme.CourseCiTheme

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AssignmentDetailScreen(
    onNavigateUp: () -> Unit,
    viewModel: AssignmentDetailViewModel = viewModel(factory = AssignmentDetailViewModel.Factory),
) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    val ready = uiState as? AssignmentDetailUiState.Ready
                    Column {
                        Text(ready?.assignment?.title.orEmpty(), maxLines = 1, overflow = TextOverflow.Ellipsis)
                        ready?.course?.let {
                            Text(
                                it.title,
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis,
                            )
                        }
                    }
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
            AssignmentDetailUiState.Loading -> LoadingContent(Modifier.padding(padding))

            AssignmentDetailUiState.NotFound -> EmptyState(
                icon = Icons.Outlined.SearchOff,
                title = "Assignment not found",
                message = "It may have been removed by the teacher.",
                modifier = Modifier.padding(padding),
            )

            is AssignmentDetailUiState.Ready -> AssignmentDetailContent(
                state = state,
                onUrlChanged = viewModel::onUrlChanged,
                onSubmit = viewModel::submit,
                modifier = Modifier.padding(padding),
            )
        }
    }
}

@Composable
private fun AssignmentDetailContent(
    state: AssignmentDetailUiState.Ready,
    onUrlChanged: () -> Unit,
    onSubmit: (String) -> Unit,
    modifier: Modifier = Modifier,
) {
    // Survives rotation; reset whenever the ViewModel reports a successful submission
    var url by rememberSaveable(state.form.resetKey) { mutableStateOf("") }

    LazyColumn(
        modifier = modifier,
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        item { AssignmentHeader(state) }
        state.grade?.let { grade -> item { GradeCard(grade, state.assignment.maxScore) } }
        item {
            if (state.canSubmit) {
                SubmitRepositoryForm(
                    url = url,
                    onUrlChange = { url = it; onUrlChanged() },
                    error = state.form.urlError,
                    isSubmitting = state.form.isSubmitting,
                    isResubmission = state.attempts.isNotEmpty(),
                    onSubmit = { onSubmit(url) },
                )
            } else {
                SubmissionLockedNote(state.state)
            }
        }
        state.latestAttempt?.let { latest ->
            item { SectionTitle("Latest attempt") }
            item(key = latest.id) { SubmissionResultCard(latest, state.now) }
        }
        val previous = state.attempts.drop(1)
        if (previous.isNotEmpty()) {
            item { SectionTitle("Previous attempts") }
            items(previous, key = { it.id }) { PreviousAttemptRow(it) }
        }
    }
}

@Composable
private fun AssignmentHeader(state: AssignmentDetailUiState.Ready) {
    val assignment = state.assignment
    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
        AssignmentStatePill(state.state)
        Text(assignment.description, style = MaterialTheme.typography.bodyLarge)
        Row(horizontalArrangement = Arrangement.spacedBy(24.dp)) {
            InfoColumn("Max score", "${assignment.maxScore.formatScore()} pts")
            InfoColumn("Due", assignment.dueAt?.formatDateTime() ?: "—")
            InfoColumn("Attempts", state.attempts.size.toString())
        }
        Text(
            dueLabel(assignment.dueAt, state.now),
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
    }
}

@Composable
private fun InfoColumn(label: String, value: String) {
    Column {
        Text(label, style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        Text(value, style = MaterialTheme.typography.titleSmall)
    }
}

@Composable
private fun GradeCard(grade: Grade, maxScore: Double) {
    val colors = CourseCiTheme.statusColors
    Card(
        colors = CardDefaults.cardColors(
            containerColor = colors.successContainer,
            contentColor = colors.onSuccessContainer,
        ),
    ) {
        Column(Modifier.fillMaxWidth().padding(16.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
            Text("Final grade", style = MaterialTheme.typography.labelLarge)
            Text("${grade.score.formatScore()} / ${maxScore.formatScore()}", style = MaterialTheme.typography.headlineMedium)
            grade.feedback?.let { Text("“$it”", style = MaterialTheme.typography.bodyMedium) }
            Text("Graded ${grade.gradedAt.formatDateTime()}", style = MaterialTheme.typography.bodySmall)
        }
    }
}

@Composable
private fun SubmissionLockedNote(state: AssignmentState) {
    val message = when (state) {
        AssignmentState.UPCOMING -> "Submissions open once the assignment starts."
        AssignmentState.GRADING -> "Your latest attempt is being graded. You can resubmit when it finishes."
        AssignmentState.GRADED -> "This assignment has been graded. Submissions are closed."
        else -> "The deadline has passed. Submissions are closed."
    }
    Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceContainer)) {
        Row(
            Modifier.fillMaxWidth().padding(16.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Icon(Icons.Outlined.Info, contentDescription = null, modifier = Modifier.size(20.dp))
            Text(message, style = MaterialTheme.typography.bodyMedium)
        }
    }
}

@Composable
private fun SectionTitle(text: String) {
    Text(text, style = MaterialTheme.typography.titleSmall, color = MaterialTheme.colorScheme.primary, modifier = Modifier.padding(top = 8.dp))
}
