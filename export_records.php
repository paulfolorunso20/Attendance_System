<?php
session_start();
include "config/db.php";
include "includes/functions.php";

require_role("lecturer");

$lecturer_id = current_user_id();
$course_id = isset($_GET["course_id"]) ? (int) $_GET["course_id"] : 0;
$date_from = trim($_GET["date_from"] ?? "");
$date_to = trim($_GET["date_to"] ?? "");

$where = ["s.lecturer_id = ?"];
$types = "i";
$params = [$lecturer_id];

if ($course_id > 0) {
    $where[] = "s.course_id = ?";
    $types .= "i";
    $params[] = $course_id;
}

if ($date_from !== "") {
    $where[] = "DATE(ar.marked_at) >= ?";
    $types .= "s";
    $params[] = $date_from;
}

if ($date_to !== "") {
    $where[] = "DATE(ar.marked_at) <= ?";
    $types .= "s";
    $params[] = $date_to;
}

$whereSql = implode(" AND ", $where);
$query = "SELECT u.full_name, u.matric_no, u.department, u.email, c.course_code, c.course_title,
                 ar.marked_at, ar.status, ar.face_verified, ar.location_verified, ar.distance_meters
FROM attendance_records ar
JOIN users u ON ar.student_id = u.id
JOIN attendance_sessions s ON ar.session_id = s.id
JOIN courses c ON s.course_id = c.id
WHERE $whereSql
ORDER BY ar.marked_at DESC";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=attendance_records_" . date("Ymd_His") . ".csv");

$output = fopen("php://output", "w");
fputcsv($output, [
    "Student Name",
    "Matric Number",
    "Department",
    "Email",
    "Course Code",
    "Course Title",
    "Date/Time",
    "Status",
    "Face Verified",
    "Location Verified",
    "Distance Meters",
]);

while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($output, [
        $row["full_name"],
        $row["matric_no"],
        $row["department"],
        $row["email"],
        $row["course_code"],
        $row["course_title"],
        $row["marked_at"],
        $row["status"],
        $row["face_verified"] ? "Verified" : "Failed",
        $row["location_verified"] ? "Verified" : "Failed",
        $row["distance_meters"],
    ]);
}

fclose($output);
exit();
