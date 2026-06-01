# Project Structure

This project is a procedural PHP attendance system organized by real feature responsibility.

It is intentionally **not** forced into MVC or a framework layout. Each browser-facing PHP page still owns its request handling and page rendering, while related pages are grouped into predictable feature folders.

## Directory Tree

```text
attendance-system/
  index.php                 Main entry point and role-based redirect
  account/                  Shared account/profile management
  admin/                    Admin dashboard, users, courses, and audit log
  assets/
    css/                    Shared application styles
  attendance/               QR attendance flow, QR image generation, live session APIs
  auth/                     Login, logout, student registration, lecturer registration
  config/                   Database connection and runtime configuration
  database/                 SQL schema and migration scripts
  includes/                 Shared bootstrap and helper functions
  lecturer/                 Lecturer dashboard, courses, sessions, records, exports
  scripts/                  Utility scripts used by backend features
  student/                  Student dashboard, course registration, attendance history
  uploads/                  Runtime uploads, ignored by Git
```

## Folder Responsibilities

### `index.php`

The only root PHP entry page. It starts the application through `includes/bootstrap.php` and redirects logged-in users to the correct dashboard:

- lecturer: `lecturer/dashboard.php`
- student: `student/dashboard.php`
- admin: `admin/dashboard.php`
- guest: `auth/login.php`

### `includes/`

Shared reusable PHP support.

- `bootstrap.php` centralizes session startup, database loading, and helper loading.
- `functions.php` contains existing shared helpers for escaping, auth context handling, redirects, audit logging, URL helpers, GPS distance, face snapshot handling, and flash messages.

This folder is deliberately small for now. The project is not yet split into service classes.

### `config/`

Contains database/runtime configuration.

- `db.php` connects to the existing MySQL database and sets timezone.
- `app.php` contains small application-level settings such as the lecturer invite code.

### `auth/`

Authentication and account creation pages:

- `login.php`
- `logout.php`
- `forgot_password.php`
- `register.php`
- `student_register.php`
- `lecturer_register.php`

Lecturer registration is protected by an invite code so students cannot freely create lecturer accounts.

### `account/`

Shared profile management for all roles:

- `profile.php`

### `student/`

Student-facing pages:

- `dashboard.php`
- `courses.php`
- `history.php`

### `lecturer/`

Lecturer-facing pages:

- `dashboard.php`
- `create_course.php`
- `create_session.php`
- `manage_sessions.php`
- `view_records.php`
- `export_records.php`

### `admin/`

Admin-facing pages:

- `dashboard.php`
- `users.php`
- `courses.php`
- `audit_log.php`

### `attendance/`

Attendance and live session feature files:

- `mark_attendance.php`
- `qr_code.php`
- `session_status.php`
- `session_extend.php`

### `assets/`

Shared frontend styles.

### `database/`

Schema and migration SQL files.

### `scripts/`

Utility scripts used by PHP. `qr_generate.js` is used by the QR image endpoint.

### `uploads/`

Runtime upload location for captured face snapshots. This folder is ignored by Git.

## Removed Transitional Files

The previous root-level compatibility wrappers were removed because active navigation now points directly to the organized feature folders.

Examples removed:

- `login.php`
- `student_dashboard.php`
- `lecturer_dashboard.php`
- `admin_dashboard.php`
- `mark_attendance.php`
- `create_session.php`
- `profile.php`
- other old root redirect wrappers

The old `includes/legacy_redirect.php` helper was also removed because it only supported those wrappers.

## Current Architecture

Current architecture:

- procedural PHP
- feature-folder organization
- centralized bootstrap
- shared helper file

It is **not** MVC, and it is **not** a full layered architecture yet.

## Remaining Technical Debt

- `includes/functions.php` still contains several different helper responsibilities.
- Some feature pages still mix database queries, request handling, HTML, and JavaScript.
- `assets/css/style.css` is large and could later be split by shared layout and feature-specific sections.
- QR generation currently depends on Node.js and `scripts/qr_generate.js`.
- Hosting will require checking `config/db.php` and base URL behavior.

These are future improvement areas, not part of this structural cleanup.
