CREATE DATABASE IF NOT EXISTS attendance_system
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE attendance_system;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'lecturer') NOT NULL,
    matric_no VARCHAR(40) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(30) NOT NULL UNIQUE,
    course_title VARCHAR(180) NOT NULL,
    lecturer_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_courses_lecturer FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS attendance_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    lecturer_id INT NOT NULL,
    session_token VARCHAR(96) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    latitude DECIMAL(10, 7) NOT NULL,
    longitude DECIMAL(10, 7) NOT NULL,
    radius_meters INT NOT NULL DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_attendance_sessions_token (session_token),
    INDEX idx_attendance_sessions_lecturer (lecturer_id),
    CONSTRAINT fk_sessions_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT fk_sessions_lecturer FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS attendance_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    student_id INT NOT NULL,
    status ENUM('present', 'rejected') NOT NULL DEFAULT 'present',
    face_verified TINYINT(1) NOT NULL DEFAULT 0,
    location_verified TINYINT(1) NOT NULL DEFAULT 0,
    latitude DECIMAL(10, 7) NULL,
    longitude DECIMAL(10, 7) NULL,
    distance_meters DECIMAL(10, 2) NULL,
    face_snapshot VARCHAR(255) NULL,
    marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_session_student (session_id, student_id),
    INDEX idx_attendance_records_student (student_id),
    CONSTRAINT fk_records_session FOREIGN KEY (session_id) REFERENCES attendance_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_records_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT INTO users (full_name, email, password, role, matric_no)
VALUES
    ('Demo Lecturer', 'lecturer@example.com', 'password', 'lecturer', NULL),
    ('Demo Student', 'student@example.com', 'password', 'student', '2022/42355')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

INSERT INTO courses (course_code, course_title, lecturer_id)
SELECT 'SEN401', 'Software Engineering Project', id
FROM users
WHERE email = 'lecturer@example.com'
ON DUPLICATE KEY UPDATE course_title = VALUES(course_title);
