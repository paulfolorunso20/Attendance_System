<?php
require_once __DIR__ . "/../includes/bootstrap.php";
require_role("student");

$student_id = current_user_id();
$student_query = "SELECT full_name, matric_no, department, email FROM users WHERE id = ? LIMIT 1";
$student_stmt = mysqli_prepare($conn, $student_query);
mysqli_stmt_bind_param($student_stmt, "i", $student_id);
mysqli_stmt_execute($student_stmt);
$student_result = mysqli_stmt_get_result($student_stmt);
$student = mysqli_fetch_assoc($student_result);

$query = "SELECT ar.*, c.course_code, c.course_title
          FROM attendance_records ar
          JOIN attendance_sessions s ON ar.session_id = s.id
          JOIN courses c ON s.course_id = c.id
          WHERE ar.student_id = ?
          ORDER BY ar.marked_at DESC";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $student_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Attendance History</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=professional-ui-5">
</head>
<body>

<div class="dashboard-container">
    <h2>Attendance History</h2>
    <div class="student-identity align-left">
        <strong><?php echo e($student["full_name"] ?? $_SESSION["full_name"]); ?></strong>
        <?php if (!empty($student["department"])) { ?>
            <span><?php echo e($student["department"]); ?></span>
        <?php } ?>
    </div>

    <table border="1" cellpadding="10" cellspacing="0" width="100%">
        <tr>
            <th>Course</th>
            <th>Date/Time</th>
            <th>Status</th>
            <th>Face</th>
            <th>Location</th>
        </tr>

        <?php while ($row = mysqli_fetch_assoc($result)) { ?>
        <tr>
            <td><?php echo e($row["course_code"] . " - " . $row["course_title"]); ?></td>
            <td><?php echo e($row["marked_at"]); ?></td>
            <td><?php echo e(ucfirst($row["status"])); ?></td>
            <td><?php echo $row["face_verified"] ? "Verified" : "Failed"; ?></td>
            <td><?php echo $row["location_verified"] ? "Verified" : "Failed"; ?></td>
        </tr>
        <?php } ?>
    </table>

    <br>
    <a href="dashboard.php">Back to Dashboard</a>
</div>

</body>
</html>
