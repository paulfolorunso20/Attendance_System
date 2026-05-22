# Attendance System Deployment Notes

## Requirements

- PHP 8.1+ with `mysqli` enabled.
- MySQL or MariaDB.
- HTTPS hosting is strongly recommended because browser camera and GPS permissions work best on secure origins.
- Node.js is currently required for local QR generation through `qr_code.php` and `qr_generate.js`.

## Database

Use the existing database schema from phpMyAdmin. Before hosting, export the local database and import it on the host.

The `users.role` column must support:

```sql
ENUM('student','lecturer','admin')
```

The `users` table also needs:

```sql
title
position
face_descriptor
```

## Default Admin

Current default admin:

```text
admin@example.com
admin123
```

Change this password immediately after deployment.

## QR Links

For local XAMPP testing, `includes/functions.php` uses `$lanHost`.

Online, the same helper will use the hosted domain automatically when the site is opened from the domain instead of `localhost`.

## Final Hosting Checklist

- Import database.
- Update `config/db.php` with hosted database credentials.
- Change admin password.
- Confirm HTTPS is active.
- Test login, lecturer session creation, QR scanning, GPS permission, face capture, and CSV export.
