package com.courseci.domain

import java.net.URI
import java.net.URISyntaxException

/** Supported public Git hosts */
val SUPPORTED_GIT_HOSTS = setOf("github.com", "gitlab.com", "bitbucket.org")

object RepositoryUrlValidator {
    private const val MAX_LENGTH = 2048
    private val SEGMENT = Regex("^[A-Za-z0-9._-]+$")

    /** Returns `null` when [input] is a valid public repository URL, otherwise an error message */
    fun validate(input: String): String? {
        val url = input.trim()
        if (url.isEmpty()) return "Enter a repository URL"
        if (url.length > MAX_LENGTH) return "URL is too long"

        val uri = try {
            URI(url)
        } catch (_: URISyntaxException) {
            return "This is not a valid URL"
        }

        if (!uri.scheme.equals("https", ignoreCase = true)) return "Only https:// URLs are supported"
        if (uri.rawUserInfo != null) return "URL must not contain credentials"
        if (uri.port != -1) return "URL must not contain a port"
        if (uri.rawQuery != null || uri.rawFragment != null) return "URL must not contain query or fragment"

        val host = uri.host?.lowercase()
        if (host !in SUPPORTED_GIT_HOSTS) return "Supported hosts: ${SUPPORTED_GIT_HOSTS.joinToString()}"

        val segments = uri.path.orEmpty().trim('/').removeSuffix(".git").split('/')
        if (segments.size != 2 || segments.any { !SEGMENT.matches(it) || it == "." || it == ".." }) {
            return "Use the form https://$host/owner/repository"
        }
        return null
    }

    /** Canonical form stored for a submission: no trailing slash or `.git` */
    fun normalize(input: String): String = input.trim().trimEnd('/').removeSuffix(".git")
}

/** Mirrors the backend registration rules: name max 255, valid email, password min 8 and confirmed */
object CredentialsValidator {
    private val EMAIL = Regex("^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$")
    const val MIN_PASSWORD_LENGTH = 8

    fun validateEmail(email: String): String? = when {
        email.isBlank() -> "Enter your email"
        !EMAIL.matches(email.trim()) -> "Enter a valid email address"
        else -> null
    }

    fun validateLoginPassword(password: String): String? =
        if (password.isEmpty()) "Enter your password" else null

    fun validateName(name: String): String? = when {
        name.isBlank() -> "Enter your name"
        name.trim().length > 255 -> "Name is too long"
        else -> null
    }

    fun validateNewPassword(password: String): String? =
        if (password.length < MIN_PASSWORD_LENGTH) "Use at least $MIN_PASSWORD_LENGTH characters" else null

    fun validateConfirmation(password: String, confirmation: String): String? =
        if (password != confirmation) "Passwords do not match" else null
}
