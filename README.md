# Task Management API

A RESTful API built with **Laravel 13** applying clean architecture principles:
Action classes, the Filterable trait pattern, multi-tenancy via Global Scopes,
Redis caching, and queue-based background processing.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 13 |
| Authentication | Laravel Sanctum (token-based) |
| Database | MySQL |
| Cache | Redis |
| Queue | Redis (via Laravel Queues) |
| Architecture | Action Classes + Filterable Trait |

---

## Project Structure

```
app/
├── Actions/
│   ├── RegisterUser.php          # Business logic: register + create tenant
│   └── Tasks/
│       ├── CreateTask.php        # Business logic: create task + dispatch job
│       ├── UpdateTask.php        # Business logic: update task + dispatch job
│       └── DeleteTask.php        # Business logic: soft delete + dispatch job
│
├── Enums/
│   └── TaskStatus.php            # Enum: todo | in_progress | done
│
├── Filters/
│   ├── QueryFilter.php           # Abstract base: maps request params to methods
│   └── TaskFilter.php            # Concrete: status, search, dueDate, sortBy
│
├── Http/
│   ├── Controllers/Api/V1/
│   │   ├── AuthController.php    # register, login, logout
│   │   └── TaskController.php    # CRUD + cache + filter
│   ├── Middleware/
│   │   └── EnsureTenantScope.php # Binds app('tenant') from authenticated user
│   ├── Requests/
│   │   ├── RegisterRequest.php
│   │   ├── LoginRequest.php
│   │   └── Task/
│   │       ├── StoreTaskRequest.php
│   │       └── UpdateTaskRequest.php
│   └── Resources/
│       ├── TaskResource.php      # Transforms Task model → JSON
│       └── UserResource.php      # Transforms User model → JSON
│
├── Jobs/
│   └── LogTaskActivity.php       # Queued job: logs activity, simulates email
│
├── Models/
│   ├── Tenant.php
│   ├── User.php
│   └── Task.php                  # Has Filterable trait + tenant GlobalScope
│
├── Policies/
│   └── TaskPolicy.php            # Authorization: tenant isolation rules
│
└── Traits/
    └── Filterable.php            # Adds scopeFilter() to any Eloquent model

database/migrations/
├── create_tenants_table.php
├── create_users_table.php        # has tenant_id FK
└── create_tasks_table.php        # has tenant_id + user_id FK, softDeletes
```

---

## Installation

```bash
# 1. Clone and install dependencies
git clone <repo-url> task-api
cd task-api
composer install

# 2. Environment setup
cp .env.example .env
php artisan key:generate

# 3. Configure your .env
DB_CONNECTION=mysql
DB_DATABASE=task_api
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# 4. Run migrations
php artisan migrate

# 5. Start queue worker (separate terminal)
php artisan queue:work

# 6. Start dev server
php artisan serve
```

---

## Authentication

This API uses **Laravel Sanctum** — token-based authentication.

Every protected request must include the token in the `Authorization` header:

```
Authorization: Bearer <your-token>
```

You get the token from `/register` or `/login`.

---

## Multi-Tenancy

Every user belongs to a **Tenant** (organization). When you register, a tenant is
automatically created for you. All your tasks are scoped to your tenant — you
cannot see or modify another tenant's data.

This is enforced at **two levels**:
1. **Global Scope** on the `Task` model — every query automatically adds `WHERE tenant_id = ?`
2. **TaskPolicy** — double-checks ownership before view / update / delete

---

## API Endpoints

Base URL: `http://localhost:8000/api/v1`

---

### Auth

#### POST `/register`

Create a new user account. A tenant (organization) is automatically created.

**Request body:**
```json
{
    "name": "Mohammed Adel",
    "email": "mohammed-adel@example.com",
    "password": "secret123",
    "password_confirmation": "secret123",
    "tenant_name": "My Company"
}
```

| Field | Type | Required | Rules |
|---|---|---|---|
| name | string | Yes | max 255 |
| email | string | Yes | valid email, unique |
| password | string | Yes | min 8 chars, must be confirmed |
| password_confirmation | string | Yes | must match password |
| tenant_name | string | No | defaults to "{name}'s Org" |

**Response `201`:**
```json
{
    "success": true,
    "message": "User registered successfully.",
    "data": {
        "user": {
            "id": 2,
            "name": "mohamed adel",
            "email": "mohammedadell30@gmail.com"
        },
        "token": "3|xGBIl01qtZBQJxjckfyDIz39VIGBpELupwpVUkSlbcb793d2"
    }
}
```

---

#### POST `/login`

**Request body:**
```json
{
    "email": "mohammedadell301@gmail.com",
    "password": "secret123"
}
```

**Response `200`:**
```json
{
    "success": true,
    "message": "Logged in successfully.",
    "data": {
        "user": {
            "id": 1,
            "name": "mohamed",
            "email": "mohammedadell301@gmail.com"
        },
        "token": "4|GzWhcspEkPWOvdvjDshoZeOdTU7VVmNC8lA1Id6j78e1d628"
    }
}
```

**Response `401` (wrong credentials):**
```json
{
    "success": false,
    "message": "Invalid credentials."
}
```

---

#### POST `/logout`

Requires: `Authorization: Bearer <token>`

**Response `200`:**
```json
{
    "success": true,
    "message": "Logged out successfully.",
    "data": null
}
```

---

### Tasks

All task endpoints require: `Authorization: Bearer <token>`

---

#### GET `/tasks`

List all tasks for the authenticated user's tenant. Paginated (15 per page). Results are cached in Redis for 60 minutes.

**Query parameters (all optional):**

| Param | Type | Description | Example |
|---|---|---|---|
| status | string | Filter by status | `?status=todo` |
| search | string | Search in title | `?search=fix+bug` |
| due_date | date | Filter by exact due date | `?due_date=2024-12-31` |
| sort_by | string | Sort column: `title`, `status`, `due_date`, `created_at` | `?sort_by=due_date` |
| sort_dir | string | `asc` or `desc` (default: `asc`) | `?sort_dir=desc` |
| page | int | Page number | `?page=2` |

**Examples:**
```
GET /api/v1/tasks
GET /api/v1/tasks?status=in_progress
GET /api/v1/tasks?search=bug&sort_by=due_date&sort_dir=asc
GET /api/v1/tasks?status=todo&page=2
```

**Response `200`:**
```json
{
    "success": true,
    "message": "Tasks retrieved successfully.",
    "data": [
        {
            "id": 6,
            "title": "task6",
            "description": "let's do this task its important",
            "status": {
                "value": "todo",
                "label": "To Do"
            },
            "due_date": "2026-06-29",
            "is_overdue": true,
            "assignee": {
                "id": 2,
                "name": "mohamed adel",
                "email": "mohammedadell30@gmail.com"
            },
            "created_at": "2026-06-29T13:30:23+00:00",
            "updated_at": "2026-06-29T13:30:23+00:00"
        },
        {
            "id": 5,
            "title": "task1",
            "description": "let's do this task its important",
            "status": {
                "value": "todo",
                "label": "To Do"
            },
            "due_date": "2026-06-29",
            "is_overdue": true,
            "assignee": {
                "id": 2,
                "name": "mohamed adel",
                "email": "mohammedadell30@gmail.com"
            },
            "created_at": "2026-06-29T13:15:13+00:00",
            "updated_at": "2026-06-29T13:15:13+00:00"
        },
        {
            "id": 4,
            "title": "task1",
            "description": "let's do this task its important",
            "status": {
                "value": "todo",
                "label": "To Do"
            },
            "due_date": "2026-06-29",
            "is_overdue": true,
            "assignee": {
                "id": 2,
                "name": "mohamed adel",
                "email": "mohammedadell30@gmail.com"
            },
            "created_at": "2026-06-29T13:12:48+00:00",
            "updated_at": "2026-06-29T13:12:48+00:00"
        },
        {
            "id": 3,
            "title": "task2",
            "description": "let's do this task its important",
            "status": {
                "value": "todo",
                "label": "To Do"
            },
            "due_date": "2026-06-29",
            "is_overdue": true,
            "assignee": {
                "id": 2,
                "name": "mohamed adel",
                "email": "mohammedadell30@gmail.com"
            },
            "created_at": "2026-06-29T13:12:27+00:00",
            "updated_at": "2026-06-29T13:12:27+00:00"
        }
    ],
    "links": {
        "first": "http://127.0.0.1:8000/api/v1/tasks?page=1",
        "last": "http://127.0.0.1:8000/api/v1/tasks?page=1",
        "prev": null,
        "next": null
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 1,
        "links": [
            {
                "url": null,
                "label": "&laquo; Previous",
                "page": null,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/v1/tasks?page=1",
                "label": "1",
                "page": 1,
                "active": true
            },
            {
                "url": null,
                "label": "Next &raquo;",
                "page": null,
                "active": false
            }
        ],
        "path": "http://127.0.0.1:8000/api/v1/tasks",
        "per_page": 15,
        "to": 4,
        "total": 4
    }
}```

---

#### POST `/tasks`

Create a new task.

**Request body:**
```json
{
    "title": "Fix login bug",
    "description": "Users can't login on mobile browsers",
    "status": "todo",
    "due_date": "2026-06-30"
}
```

| Field | Type | Required | Rules |
|---|---|---|---|
| title | string | Yes | max 255 chars |
| description | string | No | max 5000 chars |
| status | string | No | `todo`, `in_progress`, `done` — defaults to `todo` |
| due_date | date | No | must be today or future |

**Response `201`:**
```json
{
    "success": true,
    "message": "Task created successfully.",
    "data": {
        "id": 6,
        "title": "Fix login bug",
        "description": "Users can't login on mobile browsers",
        "status": {
            "value": "todo",
            "label": "To Do"
        },
        "due_date": "2026-06-30",
        "is_overdue": true,
        "assignee": {
            "id": 2,
            "name": "mohamed adel",
            "email": "mohammedadell30@gmail.com"
        },
        "created_at": "2026-06-29T13:30:23+00:00",
        "updated_at": "2026-06-29T13:30:23+00:00"
    }
}
```

---

#### GET `/tasks/{id}`

Get a single task by ID.

**Response `200`:** same shape as a single item above.

**Response `403`:** if the task belongs to a different tenant.

**Response `404`:** if the task doesn't exist.

---

#### PUT `/tasks/{id}`

Update a task. All fields are optional — only send what you want to change.

**Request body (partial update supported):**
```json
{
    "status": "in_progress"
}
```

or a full update:
```json
{
    "title": "Fix login bug — mobile",
    "description": "Updated description",
    "status": "done",
    "due_date": "2026-06-30"
}
```

**Response `200`:** updated task resource (same shape as GET).

**Response `403`:** if the task belongs to a different tenant.

---

#### DELETE `/tasks/{id}`

Soft-delete a task (it's not permanently removed from the database).

**Response `200`:**
```json
{
    "message": "Task deleted."
}
```

**Response `403`:** if the task belongs to a different tenant.

---

## Status Values

| Value | Label | Meaning |
|---|---|---|
| `todo` | To Do | Not started yet |
| `in_progress` | In Progress | Being worked on |
| `done` | Done | Completed |

---

## Error Responses

All validation errors return `422`:
```json
{
    "message": "The title field is required.",
    "errors": {
        "title": ["The title field is required."],
        "status": ["The selected status is invalid."]
    }
}
```

Unauthenticated requests return `401`:
```json
{
    "message": "Unauthenticated."
}
```

---

## Caching Strategy

Task listing (`GET /tasks`) is cached in Redis for 60 minutes.

- Cache is scoped per tenant using Redis cache tags.
- Cache key includes all query parameters (pagination, filters, sorting, search).
- Pagination metadata (`links`, `meta`) is preserved because the entire paginator instance is cached.
- Cache is automatically invalidated whenever a task is created, updated, or deleted.

Cache tags:

tenant:{tenant_id}
tasks

---

## Queue / Background Jobs

When a task is created, updated, or deleted, a `LogTaskActivity` job is dispatched to the queue.

The job:
1. Logs the activity (action, task_id, user_id, tenant_id, timestamp)
2. Is designed to be swapped for a real `Mail::send()` call

```bash
# Run the queue worker
php artisan queue:work

# Or with specific queue
php artisan queue:work --queue=default

# Monitor failed jobs
php artisan queue:failed
```

The job retries **3 times** with a **10-second backoff** on failure.

---

## AI Usage Note

AI was used as a development assistant for discussion, code review, and debugging throughout the project.

Specifically, it was used to:

* Review implementation choices and suggest alternative approaches where appropriate.
* Help troubleshoot Redis caching behavior, pagination, and cache invalidation.
* Discuss improvements to API response consistency, error handling, and project documentation.

The overall architecture—including the use of Action classes, the Filterable/QueryFilter pattern, request validation, policies, API resources, and the project structure—was designed and implemented by me. AI suggestions were reviewed, validated, and incorporated only when they aligned with the project's requirements and Laravel best practices.
