package com.courseci

import android.app.Application

class CourseCiApplication : Application() {
    val container: AppContainer by lazy { AppContainer() }
}
