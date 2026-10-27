package com.courseci

import com.courseci.data.fake.FakeAuthRepository
import com.courseci.data.fake.FakeCourseRepository
import com.courseci.data.fake.FakeTimings
import com.courseci.data.fake.seedDatabase
import com.courseci.data.repository.AuthRepository
import com.courseci.data.repository.CourseRepository
import com.courseci.domain.CourseworkInteractor
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.flow.MutableStateFlow
import java.time.Clock

/**
 * Manual dependency container living as long as the process. Swapping the
 * fake repositories for network-backed ones only touches this class
 */
class AppContainer(
    val clock: Clock = Clock.systemDefaultZone(),
    timings: FakeTimings = FakeTimings(),
) {
    private val applicationScope = CoroutineScope(SupervisorJob())
    private val database = MutableStateFlow(seedDatabase(clock))

    val authRepository: AuthRepository = FakeAuthRepository(database, timings)
    val courseRepository: CourseRepository = FakeCourseRepository(database, applicationScope, clock, timings)
    val courseworkInteractor = CourseworkInteractor(courseRepository, clock)
}
