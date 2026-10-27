package com.courseci

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import com.courseci.ui.navigation.CourseCiNavHost
import com.courseci.ui.theme.CourseCiTheme

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContent {
            CourseCiTheme {
                CourseCiNavHost()
            }
        }
    }
}
