package com.courseci.ui.courses

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.School
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.input.nestedscroll.nestedScroll
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import com.courseci.ui.components.CourseCard
import com.courseci.ui.components.EmptyState
import com.courseci.ui.components.LoadingContent
import com.courseci.ui.components.LogoutAction

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun CoursesScreen(
    onCourseClick: (courseId: Long) -> Unit,
    onLogout: () -> Unit,
    viewModel: CoursesViewModel = viewModel(factory = CoursesViewModel.Factory),
) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()
    val scrollBehavior = TopAppBarDefaults.enterAlwaysScrollBehavior()

    Scaffold(
        modifier = Modifier.nestedScroll(scrollBehavior.nestedScrollConnection),
        topBar = {
            TopAppBar(
                title = { Text("My courses") },
                actions = { LogoutAction(onLogout) },
                scrollBehavior = scrollBehavior,
            )
        },
    ) { padding ->
        when (val state = uiState) {
            CoursesUiState.Loading -> LoadingContent(Modifier.padding(padding))

            is CoursesUiState.Ready -> if (state.courses.isEmpty()) {
                EmptyState(
                    icon = Icons.Outlined.School,
                    title = "No courses yet",
                    message = "Your teacher will enroll you. Courses appear here as soon as they do.",
                    modifier = Modifier.padding(padding),
                )
            } else {
                LazyColumn(
                    contentPadding = PaddingValues(16.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp),
                    modifier = Modifier.padding(padding),
                ) {
                    item { Greeting(state) }
                    items(state.courses, key = { it.course.id }) { overview ->
                        CourseCard(overview, state.now, onClick = { onCourseClick(overview.course.id) })
                    }
                }
            }
        }
    }
}

@Composable
private fun Greeting(state: CoursesUiState.Ready) {
    Column(Modifier.padding(bottom = 4.dp)) {
        Text("Hi, ${state.studentName.substringBefore(' ')}", style = MaterialTheme.typography.headlineSmall)
        Text(
            when (state.totalToDo) {
                0 -> "You're all caught up."
                1 -> "You have 1 assignment to hand in."
                else -> "You have ${state.totalToDo} assignments to hand in."
            },
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
    }
}
