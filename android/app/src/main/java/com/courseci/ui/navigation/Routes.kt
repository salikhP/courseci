package com.courseci.ui.navigation

import kotlinx.serialization.Serializable

/** Type-safe Navigation Compose destinations. Arguments are typed constructor properties */

@Serializable
data object LoginRoute

@Serializable
data object RegisterRoute

@Serializable
data object CoursesRoute

@Serializable
data object GradesRoute

@Serializable
data class CourseDetailRoute(val courseId: Long)

@Serializable
data class AssignmentDetailRoute(val assignmentId: Long)
