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

## Stop

```bash
docker compose down
```
