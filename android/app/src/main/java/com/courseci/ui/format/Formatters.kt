package com.courseci.ui.format

import java.time.Duration
import java.time.Instant
import java.time.ZoneId
import java.time.format.DateTimeFormatter
import java.util.Locale
import kotlin.math.abs

private val dateTimeFormatter = DateTimeFormatter.ofPattern("d MMM, HH:mm", Locale.ENGLISH)

fun Instant.formatDateTime(zone: ZoneId = ZoneId.systemDefault()): String =
    dateTimeFormatter.format(atZone(zone))

/** "in 2 days", "in 5 h", "3 days ago", "just now" relative to [now] */
fun Instant.formatRelative(now: Instant): String {
    val duration = Duration.between(now, this)
    if (abs(duration.toMinutes()) < 1) return "just now"
    val hours = abs(duration.toHours())
    val text = when {
        hours < 1 -> "${abs(duration.toMinutes())} min"
        hours < 48 -> "$hours h"
        else -> "${hours / 24} days"
    }
    return if (duration.isNegative) "$text ago" else "in $text"
}

fun dueLabel(dueAt: Instant?, now: Instant): String = when {
    dueAt == null -> "No deadline"
    dueAt < now -> "Was due ${dueAt.formatRelative(now)}"
    else -> "Due ${dueAt.formatRelative(now)}"
}

/** 17.0 -> "17", 13.5 -> "13.5" */
fun Double.formatScore(): String =
    if (this % 1.0 == 0.0) toLong().toString() else String.format(Locale.ENGLISH, "%.1f", this)

fun Double.formatPercent(): String = String.format(Locale.ENGLISH, "%.0f%%", this)
