package com.courseci.data.fake

import com.courseci.data.model.CheckResult
import com.courseci.data.model.GradingStatus
import java.net.URI

/**
 * Stand-in for the backend's Docker-based Go grader. The outcome is derived
 * from the repository name so every result can be reproduced in a demo:
 *
 * - name contains `broken`  -> build fails
 * - name contains `fail`    -> tests fail
 * - name contains `slow`    -> run times out
 * - anything else           -> all checks pass
 */
object FakeGradingRunner {

    data class Outcome(val status: GradingStatus, val checks: List<CheckResult>)

    fun grade(repositoryUrl: String): Outcome {
        val repoName = runCatching { URI(repositoryUrl).path.substringAfterLast('/') }
            .getOrDefault(repositoryUrl)
            .lowercase()

        return when {
            "broken" in repoName -> Outcome(
                GradingStatus.FAILED,
                listOf(
                    CheckResult(
                        "Build", "go build ./...", passed = false,
                        output = "./main.go:12:2: undefined: total\n./main.go:18:9: missing return",
                    ),
                ),
            )

            "fail" in repoName -> Outcome(
                GradingStatus.FAILED,
                listOf(BUILD_OK, VET_OK, CheckResult("Tests", "go test ./...", passed = false, output = FAILING_TEST_OUTPUT)),
            )

            "slow" in repoName -> Outcome(
                GradingStatus.TIMED_OUT,
                listOf(
                    BUILD_OK,
                    VET_OK,
                    CheckResult("Tests", "go test ./...", passed = false, output = "panic: test timed out after 60s"),
                ),
            )

            else -> Outcome(
                GradingStatus.PASSED,
                listOf(BUILD_OK, VET_OK, CheckResult("Tests", "go test ./...", passed = true, output = "ok    courseci/solution  0.183s")),
            )
        }
    }

    private val BUILD_OK = CheckResult("Build", "go build ./...", passed = true, output = "ok")
    private val VET_OK = CheckResult("Vet", "go vet ./...", passed = true, output = "no issues found")
    private const val FAILING_TEST_OUTPUT =
        "--- FAIL: TestSolution/edge_cases (0.00s)\n    solution_test.go:42: got 0, want 1\nFAIL\nFAIL  courseci/solution  0.097s"
}
