USE `attendance system_mysql`;

ALTER TABLE attendance_sessions
    ADD COLUMN IF NOT EXISTS latitude DECIMAL(10, 7) NOT NULL DEFAULT 7.7710000,
    ADD COLUMN IF NOT EXISTS longitude DECIMAL(10, 7) NOT NULL DEFAULT 4.5569000,
    ADD COLUMN IF NOT EXISTS radius_meters INT NOT NULL DEFAULT 100,
    ADD UNIQUE KEY IF NOT EXISTS uq_attendance_sessions_token (session_token);

ALTER TABLE attendance_records
    ADD COLUMN IF NOT EXISTS latitude DECIMAL(10, 7) NULL,
    ADD COLUMN IF NOT EXISTS longitude DECIMAL(10, 7) NULL,
    ADD COLUMN IF NOT EXISTS distance_meters DECIMAL(10, 2) NULL,
    ADD COLUMN IF NOT EXISTS face_snapshot VARCHAR(255) NULL,
    ADD UNIQUE KEY IF NOT EXISTS uq_session_student (session_id, student_id);
