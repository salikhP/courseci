# CourseCI

CourseCI is organized as a monorepo. The Laravel backend submitted for this midterm is in `backend/`. Docker configuration and API testing files are kept at the repository root.

## Repository structure

```text
backend/                Laravel backend application
docker/                 Backend entrypoint and PostgreSQL initialization
postman/                Exported Postman collection and environment
docker-compose.yaml     Backend and PostgreSQL services
```

## Implemented features

- Student registration with student-only accounts
- Session-based web authentication and API token authentication
- Role-based authorization for teachers and students
- Teacher course list, detail, create, update, and archive operations
- Teacher enrollment list, add, and remove operations
- Student access to enrolled courses
- Teacher assignment creation, editing, and archiving; assignment list and detail for enrolled students
- Student repository submissions with numbered attempt history, idempotent retries, and database-queued grading
- Teacher/student submission inspection with grading status, technical feedback, and referenced grades
- Validation, pagination, PostgreSQL persistence, and seeded demonstration data

## Environment files

The working environment files are provided separately in `courseci-env-files.zip` with their repository-relative paths preserved:

```text
courseci-env-files.zip
├── .env
└── backend/
    └── .env
```

After cloning the repository, extract the archive into the repository root:

```bash
cd courseci
unzip /path/to/courseci-env-files.zip
```

The root `.env` configures Docker Compose, while `backend/.env` configures the backend application.

## Run

Docker Desktop or Docker Engine with Docker Compose v2 is required.

```bash
docker compose up --build -d
docker compose exec backend php artisan db:seed --force
```

Populate the demonstration data only once on a new database.

| Resource | Address |
| --- | --- |
| API | `http://localhost:8000/api/v1` |
| API documentation | `http://localhost:8000/docs/api` |
| OpenAPI JSON | `http://localhost:8000/docs/api.json` |
| Health check | `http://localhost:8000/up` |

Demonstration teacher account:

```text
Email: teacher@example.com
Password: password
```

## Endpoint documentation

OpenAPI: `http://localhost:8000/docs/api.json`

For manual testing in Postman, import these two files:

- `postman/CourseCI.postman_collection.json`
- `postman/CourseCI.local.postman_environment.json`

Select **CourseCI Local (Docker)** as the active Postman environment, then open the **CourseCI API** collection and use **Run collection** to execute all requests in their existing order.

## Assignment API (Phase 6)

Use the same bearer token or SPA session as the course API:

- `GET /api/v1/courses/{course}/assignments` lists active assignments, newest first, with 15 items per page. Use `?status=archived` or `?status=all` to include archived records and `page` for pagination.
- `GET /api/v1/courses/{course}/assignments/{assignment}` returns assignment details, including archived records.
- `POST /api/v1/courses/{course}/assignments` creates an assignment and its journal grade item atomically (`201`). Requires `title` and `max_score`; accepts nullable `description`, `opens_at`, and `due_at`.
- `PATCH /api/v1/courses/{course}/assignments/{assignment}` updates the supplied fields (`200`). Omitted fields stay unchanged; nullable fields can be cleared with `null`.
- `DELETE /api/v1/courses/{course}/assignments/{assignment}` archives the assignment (`204`). Repeating the request preserves the original archive timestamp. Submissions, grades, and journal history are retained.

Reading requires the owning teacher or a currently enrolled student. Writing requires the owning teacher and an active course. Archived assignments remain readable but cannot be edited. Outsiders receive `403`, an assignment under the wrong course receives `404`, and invalid input receives `422` with field errors. Manual journal entries are excluded from assignment lists.

Titles are trimmed, required, and limited to 255 characters; descriptions are trimmed and limited to 10,000 characters. Maximum score ranges from `0.01` to `999999.99`, with at most two decimal places. When both dates are set, the deadline must be strictly later than the opening date, including on partial updates. Timestamp inputs can include timezone offsets and are normalized to UTC before persistence. `null` opening/deadline means no restriction at that end of the window.

After any submission exists, `opens_at` and `max_score` cannot change (`422`). Sending equivalent values is allowed. Title, description, and deadline remain editable. Future and overdue assignments remain visible. The `is_open` response flag is true from the opening instant through the deadline instant, provided neither the course nor the assignment is archived.

Responses contain `id`, `course_id`, `grade_item_id`, `title`, `description`, `max_score` (a decimal string), nullable `opens_at`, `due_at`, `archived_at`, `is_open`, `grading_requirements`, and timestamps in UTC. Lists also contain the standard pagination links and metadata. `grading_requirements` is read-only: Go with the fixed checks `go build ./...`, `go vet ./...`, and `go test ./...`, matching the Android demo. Supplying nonempty `grading_requirements` is rejected; teachers cannot configure execution commands. Actual grading execution belongs to Phase 8.

Run the assignment feature tests against the dedicated Compose test database:

```bash
docker compose exec -e APP_ENV=testing -e DB_DATABASE=courseci_test backend php artisan test --compact tests/Feature/Api/V1/AssignmentControllerTest.php
```

For manual acceptance testing, reimport the updated Postman collection and local environment, select **CourseCI Local (Docker)**, and run the collection in order. It includes assignment creation, teacher/student reads, score validation, updates, archive/history access, and access revocation after enrollment removal. The run creates its own course and student, archives its demo records, and revokes its tokens.

## Submission API (Phase 7)

All submission routes are scoped to a course and assignment. Use a bearer token or SPA session:

- `POST /api/v1/courses/{course}/assignments/{assignment}/submissions` accepts `{"repository_url":"https://github.com/owner/repository"}`. Requires an enrolled student and an open submission window. Returns `201` with status `queued`, attempt number, and an initial queued grading run.
- `GET /api/v1/courses/{course}/assignments/{assignment}/submissions` returns a paginated history, newest first. Teachers see all attempts in their own course; students see only their own. Optional `status` filters the collection and is preserved in pagination links.
- `GET /api/v1/courses/{course}/assignments/{assignment}/submissions/{submission}` returns the attempt, latest grading result, grading-run history, and its referenced grade (or `null`). Internal runner metadata and idempotency keys are omitted.

Send an `Idempotency-Key` header containing a UUID for each intended attempt. Retrying with the same key and normalized URL returns the original attempt (`200`) without inserting another job, even after the deadline. Reusing a key with a different URL returns `409`. A new UUID creates a new attempt, including when the repository URL stays the same. Keys are scoped to the student and assignment; retain the same key when retrying an uncertain network response. Missing/invalid keys return `422` under `errors.idempotency_key`; a body field does not replace the header.

Repository URLs must use HTTPS and an exact host of `github.com`, `gitlab.com`, or `bitbucket.org`, with two path segments: owner and repository. Surrounding whitespace, a trailing slash, and the `.git` suffix are normalized. Credentials, explicit ports, query strings, fragments, encoded characters, dot traversal, extra path segments (including GitLab subgroups), local addresses, and other protocols/hosts are rejected. URL validation does not fetch the repository: public visibility, existence, and clone safety are verified asynchronously by the Phase 8 runner.

New attempts are accepted from the opening instant through the deadline instant; null dates leave the corresponding bound open. Resubmissions retain all prior attempts. Archived courses/assignments and closed windows reject new submissions with `422`. Nonstudents and unenrolled students receive `403`. Removed students retain read access to their own submission history, matching the existing submission policy; they cannot submit again. Students cannot read another student's attempts, and unrelated teachers cannot access the course's submissions. Mismatched nested IDs return `404`.

Statuses are `queued`, `running`, `passed`, `failed`, `timed_out`, and `system_error`. `grading_result` exposes structured checks, bounded stdout/stderr, exit/error codes, and timing. `grade` is the teacher grade that references this attempt; the grading API is added in Phase 9. Creating or replaying a submission never assigns an automatic score.

The submission, initial grading run, and serialized job are written in one PostgreSQL transaction. The job uses the `database` queue connection and the `grading` queue. Keep `DB_QUEUE_CONNECTION` unset so the jobs table uses the application database; workers see the job only after commit. A dispatch failure rolls back the attempt and grading run. Assignment locking serializes attempt numbering and coordinates with assignment edits/archiving. Processing is idempotent for terminal submission statuses.

Docker Compose runs the API and database. Jobs remain queued until a worker is started. **Phase 7 does not clone repositories or execute student code.** The job currently records `system_error` with `error_code: runner_unavailable` if processed; it does not fabricate a pass/fail result. After running the Postman collection, this command safely exercises the unavailable-runner path and drains the grading queue:

```bash
docker compose exec backend php artisan queue:work database --queue=grading --stop-when-empty --tries=1 --timeout=30 --no-interaction
```

The collection includes retry deduplication, resubmissions, locked assignment fields, late/unsafe submissions, teacher/student history, and post-removal history access. Leave the worker stopped while running the collection so its initial queued-status assertions remain deterministic. For automated acceptance:

```bash
docker compose exec -e APP_ENV=testing -e DB_DATABASE=courseci_test backend php artisan test --compact
```

## Stop

```bash
docker compose down
```
