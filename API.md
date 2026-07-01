# Household OS — API Documentation

**Base URL:** `http://your-domain.com/api`  
**Authentication:** Bearer Token (Laravel Passport)  
**Content-Type:** `application/json`  
**Rate Limiting:** See per-endpoint notes below

---

## Authentication

All protected endpoints require:
```
Authorization: Bearer {access_token}
```

---

## 1. Authentication Endpoints

> Rate Limit: **10 requests/minute**

---

### `POST /api/auth/register`

Register a new user. Optionally creates a household and makes the user its admin.

**Request Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `first_name` | string | ✅ | User's first name |
| `last_name` | string | ✅ | User's last name |
| `email` | string | ✅ | Unique email address |
| `password` | string | ✅ | Min 8 characters |
| `password_confirmation` | string | ✅ | Must match password |
| `phone` | string | ❌ | Phone number |
| `household_name` | string | ❌ | If provided, creates a new household and makes this user its admin |

**Success Response — `201 Created`:**
```json
{
  "success": true,
  "message": "Registration successful",
  "data": {
    "user": {
      "id": 1,
      "email": "john@example.com",
      "first_name": "John",
      "last_name": "Doe",
      "name": "John Doe",
      "phone": "+1234567890",
      "created_at": "2026-07-01T12:00:00.000000Z"
    },
    "household": { "id": 1, "name": "Doe Family" },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
    "token_type": "Bearer"
  }
}
```

**Error Responses:**
- `422` — Validation failed (duplicate email, password mismatch, etc.)

---

### `POST /api/auth/login`

Authenticate an existing user and get an access token.

**Request Body:**
| Field | Type | Required |
|-------|------|----------|
| `email` | string | ✅ |
| `password` | string | ✅ |

**Success Response — `200 OK`:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "email": "john@example.com",
      "first_name": "John",
      "last_name": "Doe",
      "name": "John Doe"
    },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
    "token_type": "Bearer"
  }
}
```

**Error Responses:**
- `401` — Invalid credentials
- `403` — Account is inactive

---

### `GET /api/auth/user` 🔒

Get the authenticated user's profile and their households.

**Success Response — `200 OK`:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "email": "john@example.com",
    "first_name": "John",
    "last_name": "Doe",
    "avatar": null,
    "households": [
      { "id": 1, "name": "Doe Family", "role": "admin" }
    ]
  }
}
```

---

### `POST /api/auth/logout` 🔒

Revoke the current access token.

**Success Response — `200 OK`:**
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

---

## 2. Members & Invitations

> Rate Limit: **60 requests/minute** (general authenticated limit)

---

### `GET /api/households/{household_id}/members` 🔒

List all active and invited members of a household.

**Permission:** Any active household member

**Success Response — `200 OK`:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "user": {
        "id": 1,
        "email": "john@example.com",
        "first_name": "John",
        "last_name": "Doe",
        "name": "John Doe",
        "avatar": null
      },
      "role": "admin",
      "status": "active",
      "joined_at": "2026-07-01T12:00:00.000000Z"
    }
  ]
}
```

---

### `POST /api/households/{household_id}/invitations` 🔒

Invite a user to the household by email.

**Permission:** Admin or Co-Admin only

**Request Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `invited_email` | string | ✅ | Email address to invite |
| `role` | string | ✅ | `admin`, `co-admin`, or `member` |

**Success Response — `201 Created`:**
```json
{
  "success": true,
  "message": "Invitation sent successfully",
  "data": {
    "id": 5,
    "invited_email": "jane@example.com",
    "token": "550e8400-e29b-41d4-a716-446655440000",
    "role": "member",
    "status": "pending",
    "expires_at": "2026-07-08T12:00:00.000000Z"
  }
}
```

**Error Responses:**
- `409` — Invitation already pending, or user is already a member
- `422` — Validation failed

---

### `POST /api/invitations/{token}/accept` 🔒

Accept a household invitation using its token.

**Permission:** Any authenticated user

**Success Response — `200 OK`:**
```json
{
  "success": true,
  "message": "Invitation accepted successfully",
  "data": {
    "household_id": 1,
    "household_name": "Doe Family",
    "role": "member"
  }
}
```

**Error Responses:**
- `404` — Invitation not found
- `409` — Already a member of the household
- `410` — Invitation expired or no longer valid

---

### `PATCH /api/households/{household_id}/members/{member_id}` 🔒

Change a member's role.

**Permission:** Admin only

**Request Body:**
| Field | Type | Required | Values |
|-------|------|----------|--------|
| `role` | string | ✅ | `admin`, `co-admin`, `member` |

**Success Response — `200 OK`:**
```json
{
  "success": true,
  "message": "Member role updated successfully",
  "data": {
    "id": 2,
    "user_id": 3,
    "role": "co-admin"
  }
}
```

**Error Responses:**
- `403` — Only admins can change roles
- `404` — Member not found
- `422` — Invalid role value

---

### `DELETE /api/households/{household_id}/members/{member_id}` 🔒

Remove a member from the household.

**Permission:** Admin only

**Success Response — `200 OK`:**
```json
{
  "success": true,
  "message": "Member removed from household successfully"
}
```

**Error Responses:**
- `403` — Not authorized, or attempting to remove yourself
- `404` — Member not found

---

## 3. Tasks

> Rate Limit: **60 requests/minute**

---

### `GET /api/households/{household_id}/tasks` 🔒

List tasks for a household with optional filters and pagination.

**Permission:** Any active household member

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `status` | string | Filter by `pending`, `in_progress`, `completed`, `on_hold` |
| `priority` | string | Filter by `low`, `medium`, `high` |
| `task_type` | string | Filter by `one-time`, `recurring`, `rotating` |
| `assigned_to` | integer | Filter by assigned user ID |
| `due_before` | date | Filter tasks due on or before this date (`YYYY-MM-DD`) |
| `per_page` | integer | Pagination size (default: 15) |

**Success Response — `200 OK`:**
```json
{
  "success": true,
  "data": [ { "id": 1, "title": "Take out trash", "status": "pending", ... } ],
  "pagination": {
    "current_page": 1,
    "per_page": 15,
    "total": 42,
    "last_page": 3
  }
}
```

---

### `POST /api/households/{household_id}/tasks` 🔒

Create a new task.

**Permission:** Any active household member

**Request Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `title` | string | ✅ | Max 255 characters |
| `task_type` | string | ✅ | `one-time`, `recurring`, `rotating` |
| `description` | string | ❌ | Task description |
| `assigned_to_user_id` | integer | ❌ | Must be an active household member |
| `due_date` | date | ❌ | `YYYY-MM-DD` |
| `frequency` | string | ❌ | `daily`, `weekly`, `monthly`, `yearly` (required for recurring) |
| `priority` | string | ❌ | `low`, `medium` (default), `high` |
| `reward_points` | integer | ❌ | Min 0, max 10000 |
| `estimated_hours` | numeric | ❌ | Min 0 |
| `icon` | string | ❌ | Max 100 characters |
| `color` | string | ❌ | Hex color e.g. `#FF5733` |
| `notes` | string | ❌ | Max 5000 characters |

**Success Response — `201 Created`**

---

### `GET /api/tasks/{task_id}` 🔒

Get full details of a single task including completion history.

**Success Response — `200 OK`:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "title": "Take out trash",
    "task_type": "recurring",
    "status": "pending",
    "priority": "medium",
    "due_date": "2026-07-05",
    "created_by": { "id": 1, "name": "John Doe" },
    "assigned_to": { "id": 2, "name": "Jane Doe" },
    "completions": [
      {
        "id": 10,
        "completed_by": { "id": 2, "name": "Jane Doe" },
        "completed_at": "2026-06-28T09:00:00.000000Z",
        "notes": "Done early",
        "photo_proof": null
      }
    ]
  }
}
```

---

### `PATCH /api/tasks/{task_id}` 🔒

Update a task's fields.

**Permission:** Admin/Co-admin (any task); Members (only their own assigned/created tasks)

**Request Body:** Any subset of task creation fields using `sometimes` validation.

**Success Response — `200 OK`**

---

### `DELETE /api/tasks/{task_id}` 🔒

Delete a task.

**Permission:** Admin, Co-admin, or task creator

**Success Response — `200 OK`:**
```json
{ "success": true, "message": "Task deleted successfully" }
```

---

### `POST /api/tasks/{task_id}/complete` 🔒

Mark a task as completed. For `recurring`/`rotating` tasks, automatically spawns the next occurrence.

**Request Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `notes` | string | ❌ | Completion notes |
| `photo_proof` | string | ❌ | Proof photo URL or path |

**Success Response — `200 OK`:**
```json
{
  "success": true,
  "message": "Task completed successfully",
  "data": {
    "id": 1,
    "status": "completed",
    "completed_at": "2026-07-01T12:43:00.000000Z",
    "next_task_id": 47
  }
}
```

**Error Responses:**
- `409` — Task is already completed

---

## 4. Renewals

> Rate Limit: **30 requests/minute** (`throttle:renewals`)

---

### `GET /api/households/{household_id}/renewals` 🔒

List all renewals with optional filters.

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `status` | string | Filter by `active`, `completed`, `cancelled`, `renewed` |
| `category` | string | Filter by category |
| `upcoming` | integer | Return renewals due within N days |

---

### `GET /api/households/{household_id}/renewals/upcoming` 🔒

List renewals due within the next N days, sorted by urgency.

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `days` | integer | 90 | Look-ahead window in days |

**Success Response — `200 OK`:**
```json
{
  "success": true,
  "data": [
    {
      "id": 3,
      "title": "Car Insurance",
      "category": "insurance",
      "renewal_date": "2026-07-15",
      "days_remaining": 14,
      "urgency": "high",
      "frequency": "annual",
      "responsible_user": { "id": 1, "name": "John Doe" }
    }
  ]
}
```

---

### `POST /api/households/{household_id}/renewals` 🔒

Create a new renewal.

**Request Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `title` | string | ✅ | Max 255 characters |
| `category` | string | ✅ | `insurance`, `passport`, `subscription`, `warranty`, `contract`, `medical`, `other` |
| `renewal_date` | date | ✅ | `YYYY-MM-DD` |
| `responsible_user_id` | integer | ✅ | Must be an active household member |
| `frequency` | string | ✅ | `annual`, `bi-annual`, `quarterly`, `monthly`, `one-time` |
| `cost` | numeric | ❌ | Min 0, Max 99999999.99 |
| `currency` | string | ❌ | 3-letter currency code e.g. `USD` |
| `notes` | string | ❌ | Max 5000 characters |

**Success Response — `201 Created`**

---

### `GET /api/renewals/{renewal_id}` 🔒

Get full renewal details including renewal history.

**Success Response — `200 OK`:**
```json
{
  "success": true,
  "data": {
    "id": 3,
    "title": "Car Insurance",
    "category": "insurance",
    "renewal_date": "2026-07-15",
    "days_remaining": 14,
    "urgency": "high",
    "cost": 1200.00,
    "currency": "USD",
    "frequency": "annual",
    "status": "active",
    "reminders": {
      "sent_90d": true,
      "sent_30d": false,
      "sent_7d": false,
      "sent_due": false
    },
    "responsible_user": { "id": 1, "name": "John Doe" },
    "history": [
      {
        "id": 1,
        "renewed_by": { "id": 1, "name": "John Doe" },
        "previous_date": "2025-07-15",
        "new_date": "2026-07-15",
        "cost": 1100.00,
        "notes": "Renewed with same provider",
        "created_at": "2025-07-14T10:00:00.000000Z"
      }
    ]
  }
}
```

---

### `PATCH /api/renewals/{renewal_id}` 🔒

Update renewal fields.

**Permission:** Admin, Co-admin, or renewal creator

**Request Body:** Any subset of creation fields (all `sometimes` validated).

---

### `DELETE /api/renewals/{renewal_id}` 🔒

Delete a renewal.

**Permission:** Admin, Co-admin, or renewal creator

---

### `POST /api/renewals/{renewal_id}/complete` 🔒

Mark a renewal as completed (renewed). Logs a history entry and resets reminder flags. For non-one-time renewals, rolls the renewal date forward.

**Request Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `new_renewal_date` | date | Conditional | Required if `frequency` is not `one-time` |
| `cost_paid` | numeric | ❌ | Actual cost paid this renewal cycle |
| `notes` | string | ❌ | Notes about this renewal completion |

**Success Response — `200 OK`:**
```json
{
  "success": true,
  "message": "Renewal completed successfully",
  "data": {
    "id": 3,
    "status": "renewed",
    "renewal_date": "2027-07-15",
    "renewal_history_entry": {
      "id": 2,
      "previous_date": "2026-07-15",
      "new_date": "2027-07-15",
      "cost": 1200.00
    }
  }
}
```

---

## 5. Documents

> **Upload Rate Limit:** 5 requests/minute (`throttle:uploads`)  
> **Download Rate Limit:** 20 requests/minute (`throttle:downloads`)

All uploaded documents are **AES-256-CBC encrypted** at rest with envelope encryption. Integrity is verified via SHA-256 checksum on every download.

---

### `GET /api/households/{household_id}/documents` 🔒

List documents accessible to the current user. Access is determined by upload ownership, shared roles, or shared user IDs.

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `category` | string | Filter by document category |
| `search` | string | Full-text search on title and description |

**Success Response — `200 OK`:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "My Passport",
      "category": "passport",
      "file_type": "pdf",
      "file_size": 204800,
      "is_encrypted": true,
      "encryption_method": "AES-256-CBC",
      "is_sensitive": false,
      "download_count": 3,
      "expiry_date": "2031-07-01",
      "uploaded_by": { "id": 1, "name": "John Doe" }
    }
  ]
}
```

---

### `POST /api/households/{household_id}/documents` 🔒

Upload a new document. File is encrypted with AES-256-CBC before storage.

**Content-Type:** `multipart/form-data`

**Request Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `file` | file | ✅ | Max 10 MB |
| `title` | string | ✅ | Max 255 characters |
| `category` | string | ✅ | `insurance`, `passport`, `medical`, `school`, `warranty`, `contract`, `deed`, `utility_bill`, `tax`, `other` |
| `description` | string | ❌ | |
| `expiry_date` | date | ❌ | Document expiry date |
| `shared_with_roles` | array | ❌ | e.g. `["admin", "co-admin"]` |
| `shared_with_users` | array | ❌ | Array of user IDs |
| `is_sensitive` | boolean | ❌ | Mark as sensitive (default: false) |

**Success Response — `201 Created`**

---

### `GET /api/documents/{document_id}/download` 🔒

Download and decrypt a document. Returns the raw file stream with appropriate headers.

**Permission:** Admin/Co-admin, document uploader, or users/roles the document is shared with

**Success Response — `200 OK`:**  
Binary file stream with headers:
```
Content-Type: application/pdf
Content-Disposition: attachment; filename="my_passport.pdf"
```

**Error Responses:**
- `403` — No access to this document
- `404` — Physical file not found on server
- `500` — Decryption or integrity check failed

---

### `DELETE /api/documents/{document_id}` 🔒

Permanently delete a document and its physical file from storage.

**Permission:** Admin, Co-admin, or document uploader

**Success Response — `200 OK`:**
```json
{ "success": true, "message": "Document deleted successfully" }
```

---

## 6. Notifications

> Rate Limit: **60 requests/minute**

---

### `GET /api/notifications` 🔒

List the authenticated user's notifications with optional filters and pagination.

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `household_id` | integer | Filter by household |
| `unread` | boolean | If `true`, returns only unread notifications |
| `limit` | integer | Page size (default: 20) |

**Success Response — `200 OK`:**
```json
{
  "success": true,
  "data": [
    {
      "id": 10,
      "notification_type": "renewal_7d",
      "title": "Renewal Reminder: Car Insurance",
      "message": "The renewal for 'Car Insurance' is due in 7 days.",
      "priority": "high",
      "status": "sent",
      "read_at": null,
      "created_at": "2026-07-01T06:00:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "total": 48
  },
  "unread_count": 12
}
```

---

### `PUT /api/notifications/{notification_id}/read` 🔒

Mark a specific notification as read.

**Success Response — `200 OK`:**
```json
{
  "success": true,
  "data": {
    "id": 10,
    "read_at": "2026-07-01T12:45:00.000000Z"
  }
}
```

---

### `POST /api/notifications/read-all` 🔒

Mark all of the user's notifications as read. Optionally scoped to a household.

**Request Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `household_id` | integer | ❌ | If provided, only marks that household's notifications as read |

**Success Response — `200 OK`:**
```json
{ "success": true, "message": "All notifications marked as read" }
```

---

### `POST /api/notifications/fcm-token` 🔒

Register or update the authenticated user's Firebase Cloud Messaging (FCM) device token for push notifications.

**Request Body:**
| Field | Type | Required |
|-------|------|----------|
| `fcm_token` | string | ✅ |

**Success Response — `200 OK`:**
```json
{ "success": true, "message": "FCM token registered successfully" }
```

---

## Error Response Format

All errors follow a consistent format:

```json
{
  "success": false,
  "message": "Human-readable error description",
  "errors": {
    "field_name": ["Validation error message"]
  }
}
```

## HTTP Status Codes

| Code | Meaning |
|------|---------|
| `200` | Success |
| `201` | Resource created |
| `401` | Unauthenticated |
| `403` | Forbidden (insufficient role/permission) |
| `404` | Resource not found |
| `409` | Conflict (duplicate, already exists) |
| `410` | Gone (invitation expired) |
| `422` | Validation failed |
| `429` | Rate limit exceeded |
| `500` | Server error (encryption/decryption failure) |

---

## Security Headers

Every API response includes:

| Header | Value |
|--------|-------|
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains; preload` |
| `Content-Security-Policy` | `default-src 'self'; frame-ancestors 'none'; ...` |
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `DENY` |
| `X-XSS-Protection` | `1; mode=block` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=()` |

---

## Rate Limits Summary

| Limit Name | Endpoints | Requests/Min |
|------------|-----------|-------------|
| `auth` | `/api/auth/register`, `/api/auth/login` | 10 |
| `general` | All protected endpoints (default) | 60 |
| `renewals` | All `/renewals` endpoints | 30 |
| `uploads` | `POST /api/households/{id}/documents` | 5 |
| `downloads` | `GET /api/documents/{id}/download` | 20 |

When exceeded, the API returns `429 Too Many Requests`.
