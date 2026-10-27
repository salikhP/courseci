package com.courseci.data.repository

import com.courseci.data.model.Assignment
import com.courseci.data.model.Course
import com.courseci.data.model.Grade
import com.courseci.data.model.Student
import com.courseci.data.model.Submission
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.StateFlow

interface AuthRepository {
    /** The signed-in student */
    val currentStudent: StateFlow<Student?>

    suspend fun login(email: String, password: String): Result<Student>

    suspend fun register(name: String, email: String, password: String): Result<Student>

    fun logout()
}

interface CourseRepository {
    fun observeCourses(studentId: Long): Flow<List<Course>>

    fun observeCourse(courseId: Long): Flow<Course?>

    fun observeAssignments(courseId: Long): Flow<List<Assignment>>

    fun observeAssignment(assignmentId: Long): Flow<Assignment?>

    fun observeSubmissions(studentId: Long): Flow<List<Submission>>

    fun observeGrades(studentId: Long): Flow<List<Grade>>

    /** Creates a new attempt and queues it for automated grading */
    suspend fun submit(studentId: Long, assignmentId: Long, repositoryUrl: String): Submission
}

class AuthException(message: String) : Exception(message)
