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

After any submission exists, `opens_at` and `max_score` cannot change (`422`). Sending equivalent values is allowed. Title, description, and deadline remain editable. Future and overdue assignments remain visible. The `is_open` response flag is true from the opening instant through the deadline instant, provided neither the course nor the assignment is archived. It describes the submission window; submission creation is implemented in Phase 7.

Responses contain `id`, `course_id`, `grade_item_id`, `title`, `description`, `max_score` (a decimal string), nullable `opens_at`, `due_at`, `archived_at`, `is_open`, `grading_requirements`, and timestamps in UTC. Lists also contain the standard pagination links and metadata. `grading_requirements` is read-only: Go with the fixed checks `go build ./...`, `go vet ./...`, and `go test ./...`, matching the Android demo. Supplying nonempty `grading_requirements` is rejected; teachers cannot configure execution commands. Actual grading execution belongs to Phase 8.

Run the assignment feature tests against the dedicated Compose test database:

```bash
docker compose exec -e APP_ENV=testing -e DB_DATABASE=courseci_test backend php artisan test --compact tests/Feature/Api/V1/AssignmentControllerTest.php
```

For manual acceptance testing, reimport the updated Postman collection and local environment, select **CourseCI Local (Docker)**, and run the collection in order. It now includes assignment creation, teacher/student reads, score validation, updates, archive/history access, and access revocation after enrollment removal. The run creates its own course and student, archives its demo records, and revokes its tokens. The locked-field behavior after submissions is covered by feature tests using submission fixtures until the submission API is available.

## Stop

```bash
docker compose down
```
