# Security & Validation Implementation - Household OS Backend

This document outlines the security architecture, input validation standards, rate limiting policies, and secure header configurations implemented in the Household OS Backend.

## 1. Input Validation (Form Requests)

All incoming request payloads are strictly validated using dedicated Laravel `FormRequest` classes. 

### Key Validation Classes
- **UpdateMemberRoleRequest** (`App\Http\Requests\UpdateMemberRoleRequest`):
  - Validates `role` to ensure only permitted roles (`admin`, `co-admin`, `member`) are assigned.
- **UpdateRenewalRequest** (`App\Http\Requests\UpdateRenewalRequest`):
  - Validates optional fields (`title`, `category`, `renewal_date`, `cost`, `currency`, `responsible_user_id`, `frequency`, `status`, `notes`) with strict bounds (e.g., non-negative numeric cost, matching enum categories).

All validation errors automatically return `422 Unprocessable Entity` status codes with structured JSON error details containing specific field errors.

## 2. Endpoint-Specific Rate Limiting

Rate limiting is enforced at the route level to protect endpoints from brute force and denial of service (DoS) attacks. Custom rate limiters are configured in `AppServiceProvider`:

| Endpoint Group | Middleware | Limit | Identifier |
| :--- | :--- | :--- | :--- |
| **Document Uploads** | `throttle:uploads` | 5 requests per minute | Authenticated User ID or IP |
| **Document Downloads** | `throttle:downloads` | 20 requests per minute | Authenticated User ID or IP |
| **Renewals Management** | `throttle:renewals` | 30 requests per minute | Authenticated User ID or IP |
| **Authentication** | `throttle:10,1` | 10 requests per minute | Authenticated User ID or IP |

## 3. Secure HTTP Response Headers

Every response sent by the backend includes modern security headers, attached globally using the `SecurityHeaders` middleware:

- **Strict-Transport-Security (HSTS)**: `max-age=31536000; includeSubDomains; preload` (forces HTTPS).
- **Content-Security-Policy (CSP)**: Restricts sources for scripts, styles, images, and fonts, and forbids framing.
- **X-Content-Type-Options**: `nosniff` (prevents MIME type sniffing).
- **X-Frame-Options**: `DENY` (prevents clickjacking attacks).
- **X-XSS-Protection**: `1; mode=block` (legacy XSS filtering).
- **Referrer-Policy**: `strict-origin-when-cross-origin`.
- **Permissions-Policy**: Disables access to sensitive browser APIs (camera, microphone, geolocation, etc.).

## 4. Secure Job Implementations

Services invoked in background jobs (such as `CheckRenewalsJob`) resolve dependencies dynamically via Laravel's Service Container (e.g., `app(NotificationService::class)->send()`). This decouples static classes, avoids tight coupling, and facilitates robust mocking during testing.
