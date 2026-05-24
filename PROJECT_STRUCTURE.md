# Project Structure

The application is organized by feature area so related files are easier to find and maintain.

```text
attendance-system/
  account/        Shared account/profile pages
  admin/          Admin dashboard, user, course, and audit management
  assets/         CSS and frontend assets
  attendance/     QR attendance marking, QR generation, and live session endpoints
  auth/           Login, logout, student registration, lecturer registration
  config/         Database connection and environment configuration
  database/       SQL schema and migration scripts
  includes/       Shared PHP helper functions
  lecturer/       Lecturer dashboard, sessions, courses, reports, exports
  scripts/        Node scripts used by backend utilities
  student/        Student dashboard, course registration, history
  uploads/        Runtime uploads, ignored by Git
```

Root-level PHP files are compatibility redirects. They keep old links such as `login.php`, `mark_attendance.php`, and `create_session.php` working while the real implementation lives inside the organized folders.
