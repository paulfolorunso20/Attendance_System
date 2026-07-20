<?php
require_once __DIR__ . "/../includes/bootstrap.php";
require_role("student");

$student_id = current_user_id();

$student_query = "SELECT full_name, matric_no, department, profile_image FROM users WHERE id = ? LIMIT 1";
$student_stmt = mysqli_prepare($conn, $student_query);
mysqli_stmt_bind_param($student_stmt, "i", $student_id);
mysqli_stmt_execute($student_stmt);
$student_result = mysqli_stmt_get_result($student_stmt);
$student = mysqli_fetch_assoc($student_result) ?: [];
$studentName = $student["full_name"] ?? $_SESSION["full_name"];

$summary_query = "SELECT
    COUNT(DISTINCT s.id) AS total_sessions,
    COUNT(DISTINCT ar.session_id) AS attended_sessions
FROM student_courses sc
JOIN attendance_sessions s ON sc.course_id = s.course_id
LEFT JOIN attendance_records ar ON ar.session_id = s.id AND ar.student_id = sc.student_id
WHERE sc.student_id = ?";
$summary_stmt = mysqli_prepare($conn, $summary_query);
mysqli_stmt_bind_param($summary_stmt, "i", $student_id);
mysqli_stmt_execute($summary_stmt);
$summary_result = mysqli_stmt_get_result($summary_stmt);
$summary = mysqli_fetch_assoc($summary_result);

$totalSessions = (int) ($summary["total_sessions"] ?? 0);
$attendedSessions = (int) ($summary["attended_sessions"] ?? 0);
$missedSessions = max(0, $totalSessions - $attendedSessions);
$percentage = $totalSessions > 0 ? round(($attendedSessions / $totalSessions) * 100, 1) : 0;
$eligible = $totalSessions > 0 && $percentage >= 75;
$rateStatusClass = $percentage >= 75 ? "student-stat-good" : ($percentage >= 50 ? "student-stat-warning" : "student-stat-danger");

$course_query = "SELECT c.course_code, c.course_title,
    COUNT(DISTINCT s.id) AS total_sessions,
    COUNT(DISTINCT ar.session_id) AS attended_sessions
FROM student_courses sc
JOIN courses c ON sc.course_id = c.id
LEFT JOIN attendance_sessions s ON s.course_id = c.id
LEFT JOIN attendance_records ar ON ar.session_id = s.id AND ar.student_id = sc.student_id
WHERE sc.student_id = ?
GROUP BY c.id, c.course_code, c.course_title
ORDER BY c.course_code";
$course_stmt = mysqli_prepare($conn, $course_query);
mysqli_stmt_bind_param($course_stmt, "i", $student_id);
mysqli_stmt_execute($course_stmt);
$course_result = mysqli_stmt_get_result($course_stmt);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../assets/css/style.css?v=student-dashboard-polish-1">
</head>
<body class="role-dashboard-page student-dashboard-page">

<main class="role-dashboard-shell student-dashboard-shell">
    <section class="role-dashboard-header student-profile-header student-dashboard-reveal">
        <div class="student-profile-main">
            <div class="dashboard-user-avatar student-avatar">
                <?php if (!empty($student["profile_image"])) { ?>
                    <img src="<?php echo e(media_url($student["profile_image"])); ?>" alt="<?php echo e($studentName); ?> profile picture">
                <?php } else { ?>
                    <?php echo e(strtoupper(substr($studentName, 0, 1))); ?>
                <?php } ?>
            </div>
            <div class="student-profile-copy">
                <p class="section-kicker">Student Workspace</p>
                <h2>Welcome, <?php echo e($studentName); ?></h2>
                <div class="student-dashboard-meta">
                    <?php if (!empty($student["department"])) { ?>
                        <span><?php echo e($student["department"]); ?></span>
                    <?php } ?>
                    <?php if (!empty($student["matric_no"])) { ?>
                        <span><?php echo e($student["matric_no"]); ?></span>
                    <?php } ?>
                </div>
                <p class="welcome">Track your attendance, register courses, and continue any QR attendance session from here.</p>
            </div>
        </div>
        <div class="role-header-actions student-header-actions">
            <a href="<?php echo e(with_context("account/profile.php")); ?>" class="button-link secondary-action">Profile</a>
            <a href="<?php echo e(with_context("auth/logout.php")); ?>" class="button-link danger-action">Logout</a>
        </div>
    </section>

    <section class="student-dashboard-section student-dashboard-reveal">
        <div class="student-section-heading">
            <div>
                <p class="section-kicker">Attendance Summary</p>
                <h3>Your class participation at a glance</h3>
            </div>
        </div>

        <div class="role-stats-grid student-stats-grid">
            <div class="role-stat-card student-stat-card student-stat-good">
                <?php echo dashboard_icon("check"); ?>
                <span>Classes Attended</span>
                <strong><?php echo e($attendedSessions); ?></strong>
                <small>Sessions you have successfully marked</small>
            </div>
            <div class="role-stat-card student-stat-card student-stat-info">
                <?php echo dashboard_icon("calendar"); ?>
                <span>Total Classes</span>
                <strong><?php echo e($totalSessions); ?></strong>
                <small>Sessions from your registered courses</small>
            </div>
            <div class="role-stat-card student-stat-card student-stat-warning">
                <?php echo dashboard_icon("alert"); ?>
                <span>Missed Classes</span>
                <strong><?php echo e($missedSessions); ?></strong>
                <small>Sessions not found in your attendance record</small>
            </div>
            <div class="role-stat-card student-stat-card <?php echo e($rateStatusClass); ?>">
                <?php echo dashboard_icon("percent"); ?>
                <span>Attendance Rate</span>
                <strong><?php echo e($percentage); ?>%</strong>
                <small>Minimum expected attendance is 75%</small>
            </div>
        </div>
    </section>

    <section class="eligibility-badge student-status-alert student-dashboard-reveal <?php echo $eligible ? 'eligible' : 'not-eligible'; ?>">
        <span class="student-status-icon"><?php echo dashboard_icon($eligible ? "check" : "alert"); ?></span>
        <span>
            <strong><?php echo $eligible ? "Eligible Status" : "Attendance Status"; ?></strong>
            <span>
                <?php if ($totalSessions === 0) { ?>
                    Register your courses to begin tracking eligibility.
                <?php } elseif ($eligible) { ?>
                    Eligible for examination based on 75% attendance requirement.
                <?php } else { ?>
                    Not yet eligible for examination based on 75% attendance requirement.
                <?php } ?>
            </span>
        </span>
    </section>

    <section class="role-panel student-course-panel student-dashboard-reveal">
        <div class="panel-heading">
            <div>
                <p class="section-kicker">Course Breakdown</p>
                <h3>Course Attendance</h3>
            </div>
            <span><?php echo e($percentage); ?>% overall</span>
        </div>
        <div class="table-wrap">
            <table border="1" cellpadding="10" cellspacing="0" width="100%" class="student-attendance-table">
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Attended</th>
                        <th>Total</th>
                        <th>Percentage</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($course = mysqli_fetch_assoc($course_result)) {
                    $courseTotal = (int) $course["total_sessions"];
                    $courseAttended = (int) $course["attended_sessions"];
                    $coursePercentage = $courseTotal > 0 ? round(($courseAttended / $courseTotal) * 100, 1) : 0;
                ?>
                <tr>
                    <td><?php echo e($course["course_code"] . " - " . $course["course_title"]); ?></td>
                    <td><?php echo e($courseAttended); ?></td>
                    <td><?php echo e($courseTotal); ?></td>
                    <td><?php echo e($coursePercentage); ?>%</td>
                    <td>
                        <span class="status-badge <?php echo ($courseTotal > 0 && $coursePercentage >= 75) ? "status-active" : "status-expired"; ?>">
                            <?php echo ($courseTotal > 0 && $coursePercentage >= 75) ? "Eligible" : "Below 75%"; ?>
                        </span>
                    </td>
                </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="student-dashboard-section student-dashboard-reveal">
        <div class="student-section-heading">
            <div>
                <p class="section-kicker">Quick Actions</p>
                <h3>What would you like to do next?</h3>
            </div>
        </div>
        <div class="dashboard-grid role-action-grid student-action-grid">
        <?php if (!empty($_SESSION["pending_attendance_token"])) { ?>
        <a href="<?php echo e(with_context("attendance/mark_attendance.php?token=" . urlencode($_SESSION["pending_attendance_token"]))); ?>" class="dashboard-card student-action-card">
            <span class="action-icon"><?php echo dashboard_icon("qr"); ?></span>
            <h3>Scan QR Code</h3>
            <p>Continue the attendance session you opened from the QR code.</p>
            <span class="student-action-button">Open</span>
        </a>
        <?php } else { ?>
        <div class="dashboard-card student-action-card">
            <span class="action-icon"><?php echo dashboard_icon("qr"); ?></span>
            <h3>Scan QR Code</h3>
            <p>Use your phone camera to scan the QR code displayed by your lecturer.</p>
            <span class="student-action-button">Open</span>
        </div>
        <?php } ?>

        <a href="courses.php" class="dashboard-card student-action-card">
            <span class="action-icon"><?php echo dashboard_icon("book"); ?></span>
            <h3>My Courses</h3>
            <p>Register the courses you are taking so attendance percentage is calculated correctly.</p>
            <span class="student-action-button">Open</span>
        </a>

        <a href="history.php" class="dashboard-card student-action-card">
            <span class="action-icon"><?php echo dashboard_icon("table"); ?></span>
            <h3>Attendance History</h3>
            <p>View your submitted attendance records and verification status.</p>
            <span class="student-action-button">Open</span>
        </a>

        </div>
    </section>
</main>

<script>
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".student-dashboard-reveal").forEach(function (item, index) {
        item.style.transitionDelay = (index * 55) + "ms";
        item.classList.add("is-visible");
    });
});
</script>

</body>
</html>
