package com.courseci.ui.navigation

import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.consumeWindowInsets
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.Grading
import androidx.compose.material.icons.outlined.School
import androidx.compose.material3.Icon
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.navigation.NavDestination
import androidx.navigation.NavDestination.Companion.hasRoute
import androidx.navigation.NavDestination.Companion.hierarchy
import androidx.navigation.NavHostController
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import com.courseci.ui.SessionViewModel
import com.courseci.ui.assignment.AssignmentDetailScreen
import com.courseci.ui.auth.LoginScreen
import com.courseci.ui.auth.RegisterScreen
import com.courseci.ui.course.CourseDetailScreen
import com.courseci.ui.courses.CoursesScreen
import com.courseci.ui.grades.GradesScreen

private data class TopLevelDestination(val route: Any, val label: String, val icon: ImageVector)

private val topLevelDestinations = listOf(
    TopLevelDestination(CoursesRoute, "Courses", Icons.Outlined.School),
    TopLevelDestination(GradesRoute, "Grades", Icons.AutoMirrored.Outlined.Grading),
)

private fun NavDestination?.isTopLevel(route: Any): Boolean =
    this?.hierarchy?.any { it.hasRoute(route::class) } == true

private fun NavDestination?.isAuth(): Boolean =
    this?.hasRoute(LoginRoute::class) == true || this?.hasRoute(RegisterRoute::class) == true

@Composable
fun CourseCiNavHost(
    sessionViewModel: SessionViewModel = viewModel(factory = SessionViewModel.Factory),
) {
    val navController = rememberNavController()
    val student by sessionViewModel.currentStudent.collectAsStateWithLifecycle()
    val isSignedIn = student != null
    // Chosen once; later session changes are handled by the effect below
    val startDestination: Any = remember { if (isSignedIn) CoursesRoute else LoginRoute }

    val backStackEntry by navController.currentBackStackEntryAsState()
    val currentDestination = backStackEntry?.destination
    val showBottomBar = topLevelDestinations.any { currentDestination.isTopLevel(it.route) }

    // Auth gate: leave the auth screens after sign-in, return to login after sign-out
    LaunchedEffect(isSignedIn) {
        val destination = navController.currentDestination
        when {
            isSignedIn && destination.isAuth() -> navController.navigateClearingBackStack(CoursesRoute)
            !isSignedIn && !destination.isAuth() -> navController.navigateClearingBackStack(LoginRoute)
        }
    }

    Scaffold(
        contentWindowInsets = WindowInsets(0),
        bottomBar = {
            if (showBottomBar) {
                NavigationBar {
                    topLevelDestinations.forEach { destination ->
                        NavigationBarItem(
                            selected = currentDestination.isTopLevel(destination.route),
                            onClick = { navController.navigateToTopLevel(destination.route) },
                            icon = { Icon(destination.icon, contentDescription = null) },
                            label = { Text(destination.label) },
                        )
                    }
                }
            }
        },
    ) { innerPadding ->
        NavHost(
            navController = navController,
            startDestination = startDestination,
            modifier = Modifier
                .padding(innerPadding)
                .consumeWindowInsets(innerPadding),
        ) {
            composable<LoginRoute> {
                LoginScreen(onNavigateToRegister = { navController.navigate(RegisterRoute) })
            }
            composable<RegisterRoute> {
                RegisterScreen(onNavigateBack = { navController.popBackStack() })
            }
            composable<CoursesRoute> {
                CoursesScreen(
                    onCourseClick = { navController.navigate(CourseDetailRoute(it)) },
                    onLogout = sessionViewModel::logout,
                )
            }
            composable<GradesRoute> {
                GradesScreen(
                    onCourseClick = { navController.navigate(CourseDetailRoute(it)) },
                    onAssignmentClick = { navController.navigate(AssignmentDetailRoute(it)) },
                    onLogout = sessionViewModel::logout,
                )
            }
            composable<CourseDetailRoute> {
                CourseDetailScreen(
                    onAssignmentClick = { navController.navigate(AssignmentDetailRoute(it)) },
                    onNavigateUp = { navController.navigateUp() },
                )
            }
            composable<AssignmentDetailRoute> {
                AssignmentDetailScreen(onNavigateUp = { navController.navigateUp() })
            }
        }
    }
}

/** Bottom-bar navigation: one copy of each tab, with its back stack saved and restored */
private fun NavHostController.navigateToTopLevel(route: Any) {
    navigate(route) {
        // Courses is the root of the signed-in back stack (the start destination may be Login)
        popUpTo<CoursesRoute> { saveState = true }
        launchSingleTop = true
        restoreState = true
    }
}

private fun NavHostController.navigateClearingBackStack(route: Any) {
    navigate(route) {
        popUpTo(graph.id) { inclusive = true }
        launchSingleTop = true
    }
}
