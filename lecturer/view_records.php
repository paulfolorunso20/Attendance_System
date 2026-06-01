<?php
require_once __DIR__ . "/../includes/bootstrap.php";
require_role("lecturer");

$lecturer_id = current_user_id();
$course_id = isset($_GET["course_id"]) ? (int) $_GET["course_id"] : 0;
$date_from = trim($_GET["date_from"] ?? "");
$date_to = trim($_GET["date_to"] ?? "");

$courses_query = "SELECT id, course_code, course_title FROM courses WHERE lecturer_id = ? ORDER BY course_code";
$courses_stmt = mysqli_prepare($conn, $courses_query);
mysqli_stmt_bind_param($courses_stmt, "i", $lecturer_id);
mysqli_stmt_execute($courses_stmt);
$courses_result = mysqli_stmt_get_result($courses_stmt);

$courses = [];
while ($course = mysqli_fetch_assoc($courses_result)) {
    $courses[] = $course;
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

$query = "SELECT ar.*, u.full_name, u.matric_no, u.department, u.email, c.course_code, c.course_title
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

$records = [];
$verifiedFace = 0;
$verifiedLocation = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $records[] = $row;
    $verifiedFace += (int) $row["face_verified"];
    $verifiedLocation += (int) $row["location_verified"];
}

$totalRecords = count($records);
$exportQuery = http_build_query([
    "course_id" => $course_id,
    "date_from" => $date_from,
    "date_to" => $date_to,
]);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Attendance Records</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=professional-ui-5">
</head>
<body>

<div class="dashboard-container">

<h2>Attendance Records</h2>

<form method="GET" class="report-filter">
    <div>
        <label>Course</label>
        <select name="course_id">
            <option value="0">All Courses</option>
            <?php foreach ($courses as $course) { ?>
                <option value="<?php echo e($course["id"]); ?>" <?php echo $course_id === (int) $course["id"] ? "selected" : ""; ?>>
                    <?php echo e($course["course_code"] . " - " . $course["course_title"]); ?>
                </option>
            <?php } ?>
        </select>
    </div>

    <div>
        <label>From</label>
        <input type="date" name="date_from" value="<?php echo e($date_from); ?>">
    </div>

    <div>
        <label>To</label>
        <input type="date" name="date_to" value="<?php echo e($date_to); ?>">
    </div>

    <div class="filter-actions">
        <button type="submit">Filter Records</button>
        <a href="view_records.php" class="button-link muted-link">Clear</a>
    </div>
</form>

<div class="summary-grid">
    <div class="summary-card">
        <strong><?php echo e($totalRecords); ?></strong>
        <span>Total Records</span>
    </div>
    <div class="summary-card">
        <strong><?php echo e($verifiedFace); ?></strong>
        <span>Face Verified</span>
    </div>
    <div class="summary-card">
        <strong><?php echo e($verifiedLocation); ?></strong>
        <span>Location Verified</span>
    </div>
</div>

<div class="table-actions">
    <a href="export_records.php?<?php echo e($exportQuery); ?>" class="button-link">Export CSV</a>
</div>

<div class="table-wrap">
<table border="1" cellpadding="10" cellspacing="0" width="100%">
    <tr>
        <th>Student Name</th>
        <th>Matric No.</th>
        <th>Department</th>
        <th>Email</th>
        <th>Course</th>
        <th>Date/Time</th>
        <th>Face</th>
        <th>Location</th>
        <th>Distance</th>
    </tr>

    <?php if ($totalRecords === 0) { ?>
    <tr>
        <td colspan="9">No attendance records found.</td>
    </tr>
    <?php } ?>

    <?php foreach ($records as $row) { ?>
    <tr>
        <td><?php echo e($row['full_name']); ?></td>
        <td><?php echo e($row['matric_no']); ?></td>
        <td><?php echo e($row['department']); ?></td>
        <td><?php echo e($row['email']); ?></td>
        <td><?php echo e($row['course_code']); ?></td>
        <td><?php echo e($row['marked_at']); ?></td>
        <td><?php echo $row['face_verified'] ? 'Verified' : 'Failed'; ?></td>
        <td><?php echo $row['location_verified'] ? 'Verified' : 'Failed'; ?></td>
        <td><?php echo e(round((float) $row['distance_meters'])) . 'm'; ?></td>
    </tr>
    <?php } ?>

</table>
</div>

<br>
<a href="dashboard.php">Back to Dashboard</a>

</div>

</body>
</html>
