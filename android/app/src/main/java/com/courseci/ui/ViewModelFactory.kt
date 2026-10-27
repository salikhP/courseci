package com.courseci.ui

import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider.AndroidViewModelFactory.Companion.APPLICATION_KEY
import androidx.lifecycle.createSavedStateHandle
import androidx.lifecycle.viewmodel.CreationExtras
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import com.courseci.AppContainer
import com.courseci.CourseCiApplication

/**
 * Builds a ViewModel factory that receives the [AppContainer] and the
 * destination's [SavedStateHandle]. The handle carries the typed navigation
 * route, so arguments survive rotation and process death
 */
inline fun <reified VM : ViewModel> appViewModelFactory(
    crossinline create: (container: AppContainer, savedStateHandle: SavedStateHandle) -> VM,
) = viewModelFactory {
    initializer {
        create(appContainer(), createSavedStateHandle())
    }
}

fun CreationExtras.appContainer(): AppContainer =
    (this[APPLICATION_KEY] as CourseCiApplication).container
