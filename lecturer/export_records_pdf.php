<?php
require_once __DIR__ . "/../includes/bootstrap.php";
require_role("lecturer");

$lecturer_id = current_user_id();
$course_id = isset($_GET["course_id"]) ? (int) $_GET["course_id"] : 0;
$date_from = trim($_GET["date_from"] ?? "");
$date_to = trim($_GET["date_to"] ?? "");

$lecturer_query = "SELECT full_name, title, position FROM users WHERE id = ? LIMIT 1";
$lecturer_stmt = mysqli_prepare($conn, $lecturer_query);
mysqli_stmt_bind_param($lecturer_stmt, "i", $lecturer_id);
mysqli_stmt_execute($lecturer_stmt);
$lecturer = mysqli_fetch_assoc(mysqli_stmt_get_result($lecturer_stmt)) ?: [];
$lecturer_name = lecturer_display_name($lecturer["title"] ?? "", $lecturer["position"] ?? "", $lecturer["full_name"] ?? "Lecturer");

$course_label = "All courses";
if ($course_id > 0) {
    $course_query = "SELECT course_code, course_title FROM courses WHERE id = ? AND lecturer_id = ? LIMIT 1";
    $course_stmt = mysqli_prepare($conn, $course_query);
    mysqli_stmt_bind_param($course_stmt, "ii", $course_id, $lecturer_id);
    mysqli_stmt_execute($course_stmt);
    $course = mysqli_fetch_assoc(mysqli_stmt_get_result($course_stmt));
    if ($course) {
        $course_label = $course["course_code"] . " - " . $course["course_title"];
    }
}

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

$rows = [];
while ($row = mysqli_fetch_assoc($result)) {
    $rows[] = [
        $row["full_name"],
        $row["matric_no"],
        $row["department"],
        $row["email"],
        trim($row["course_code"] . " " . $row["course_title"]),
        date("M j, Y g:i A", strtotime($row["marked_at"])),
        $row["face_verified"] ? "Verified" : "Failed",
        $row["location_verified"] ? "Verified" : "Failed",
        round((float) $row["distance_meters"]) . "m",
    ];
}

$date_range = "All dates";
if ($date_from !== "" || $date_to !== "") {
    $date_range = ($date_from !== "" ? $date_from : "Start") . " to " . ($date_to !== "" ? $date_to : "Today");
}

stream_table_pdf(
    "attendance_records_" . date("Ymd_His") . ".pdf",
    "Attendance Records",
    [
        "Course" => $course_label,
        "Date range" => $date_range,
        "Lecturer" => $lecturer_name,
    ],
    [
        "Student Name",
        "Matric No.",
        "Department",
        "Email",
        "Course",
        "Date/Time",
        "Face",
        "Location",
        "Distance",
    ],
    $rows
);
