<?php
require_once __DIR__ . "/../includes/bootstrap.php";
require_role("admin");
$flash = get_flash();

function dashboard_count($conn, $query)
{
    $result = mysqli_query($conn, $query);

    if (!$result) {
        return 0;
    }

    $row = mysqli_fetch_row($result);
    return (int) ($row[0] ?? 0);
}

function dashboard_fetch_all($conn, $query)
{
    $result = mysqli_query($conn, $query);

    if (!$result) {
        return [];
    }

    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

$totalUsers = dashboard_count($conn, "SELECT COUNT(*) FROM users");
$totalStudents = dashboard_count($conn, "SELECT COUNT(*) FROM users WHERE role = 'student'");
$totalLecturers = dashboard_count($conn, "SELECT COUNT(*) FROM users WHERE role = 'lecturer'");
$totalAdmins = dashboard_count($conn, "SELECT COUNT(*) FROM users WHERE role = 'admin'");
$totalCourses = dashboard_count($conn, "SELECT COUNT(*) FROM courses");
$assignedCourses = dashboard_count($conn, "SELECT COUNT(*) FROM courses WHERE lecturer_id IS NOT NULL");
$totalSessions = dashboard_count($conn, "SELECT COUNT(*) FROM attendance_sessions");
$activeSessions = dashboard_count($conn, "SELECT COUNT(*) FROM attendance_sessions WHERE closed_at IS NULL AND expires_at >= NOW()");
$closedSessions = dashboard_count($conn, "SELECT COUNT(*) FROM attendance_sessions WHERE closed_at IS NOT NULL");
$expiredSessions = dashboard_count($conn, "SELECT COUNT(*) FROM attendance_sessions WHERE closed_at IS NULL AND expires_at < NOW()");
$attendanceRecords = dashboard_count($conn, "SELECT COUNT(*) FROM attendance_records");
$verifiedAttendance = dashboard_count($conn, "SELECT COUNT(*) FROM attendance_records WHERE face_verified = 1 AND location_verified = 1");
$enrolledStudents = dashboard_count($conn, "SELECT COUNT(DISTINCT student_id) FROM student_courses");
$totalEnrollments = dashboard_count($conn, "SELECT COUNT(*) FROM student_courses");
$adminProfile = null;
$adminProfileStmt = mysqli_prepare($conn, "SELECT full_name, profile_image FROM users WHERE id = ? LIMIT 1");
$adminId = current_user_id();
mysqli_stmt_bind_param($adminProfileStmt, "i", $adminId);
mysqli_stmt_execute($adminProfileStmt);
$adminProfileResult = mysqli_stmt_get_result($adminProfileStmt);
$adminProfile = mysqli_fetch_assoc($adminProfileResult) ?: [];

$recentSessions = dashboard_fetch_all($conn, "
    SELECT s.id, s.created_at, s.expires_at, s.closed_at, c.course_code, c.course_title, u.full_name AS lecturer_name,
        COUNT(ar.id) AS marked_count
    FROM attendance_sessions s
    JOIN courses c ON c.id = s.course_id
    JOIN users u ON u.id = s.lecturer_id
    LEFT JOIN attendance_records ar ON ar.session_id = s.id
    GROUP BY s.id, s.created_at, s.expires_at, s.closed_at, c.course_code, c.course_title, u.full_name
    ORDER BY s.created_at DESC
    LIMIT 5
");

$recentAttendance = dashboard_fetch_all($conn, "
    SELECT ar.marked_at, ar.face_verified, ar.location_verified, ar.status,
        student.full_name AS student_name, student.matric_no, c.course_code
    FROM attendance_records ar
    JOIN users student ON student.id = ar.student_id
    JOIN attendance_sessions s ON s.id = ar.session_id
    JOIN courses c ON c.id = s.course_id
    ORDER BY ar.marked_at DESC
    LIMIT 5
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=professional-ui-5">
</head>
<body class="admin-dashboard-page">

<div class="admin-dashboard-shell">
    <div class="admin-overview-header">
        <div class="dashboard-user-avatar">
            <?php if (!empty($adminProfile["profile_image"])) { ?>
                <img src="<?php echo e(media_url($adminProfile["profile_image"])); ?>" alt="<?php echo e($_SESSION["full_name"]); ?> profile picture">
            <?php } else { ?>
                <?php echo e(strtoupper(substr($_SESSION["full_name"], 0, 1))); ?>
            <?php } ?>
        </div>
        <div>
            <p class="section-kicker">System Overview</p>
            <h2>Admin Dashboard</h2>
            <p class="welcome">Welcome back, <?php echo e($_SESSION["full_name"]); ?>. Here is the current attendance system activity.</p>
        </div>
        <div class="admin-header-actions">
            <a href="<?php echo e(with_context("account/profile.php")); ?>" class="button-link secondary-action">Profile</a>
            <a href="<?php echo e(with_context("auth/logout.php")); ?>" class="button-link danger-action">Logout</a>
        </div>
    </div>

    <?php if ($flash) { ?>
        <p class="alert alert-<?php echo e($flash["type"]); ?>"><?php echo e($flash["message"]); ?></p>
    <?php } ?>

    <div class="overview-stats-grid">
        <div class="overview-stat-card">
            <?php echo dashboard_icon("users"); ?>
            <span>Users</span>
            <strong><?php echo number_format($totalUsers); ?></strong>
            <small><?php echo number_format($totalStudents); ?> students · <?php echo number_format($totalLecturers); ?> lecturers · <?php echo number_format($totalAdmins); ?> admins</small>
        </div>
        <div class="overview-stat-card">
            <?php echo dashboard_icon("book"); ?>
            <span>Courses</span>
            <strong><?php echo number_format($totalCourses); ?></strong>
            <small><?php echo number_format($assignedCourses); ?> assigned to lecturers</small>
        </div>
        <div class="overview-stat-card">
            <?php echo dashboard_icon("calendar"); ?>
            <span>Sessions</span>
            <strong><?php echo number_format($totalSessions); ?></strong>
            <small><?php echo number_format($activeSessions); ?> active · <?php echo number_format($expiredSessions); ?> expired · <?php echo number_format($closedSessions); ?> closed</small>
        </div>
        <div class="overview-stat-card">
            <?php echo dashboard_icon("check"); ?>
            <span>Attendance</span>
            <strong><?php echo number_format($attendanceRecords); ?></strong>
            <small><?php echo number_format($verifiedAttendance); ?> fully verified submissions</small>
        </div>
        <div class="overview-stat-card">
            <?php echo dashboard_icon("layers"); ?>
            <span>Enrollments</span>
            <strong><?php echo number_format($totalEnrollments); ?></strong>
            <small><?php echo number_format($enrolledStudents); ?> students enrolled in at least one course</small>
        </div>
    </div>

    <div class="dashboard-grid">
        <a href="<?php echo e(with_context("admin/users.php")); ?>" class="dashboard-card">
            <h3>Manage Users</h3>
            <p>View students, lecturers, and admin accounts.</p>
        </a>

        <a href="<?php echo e(with_context("admin/courses.php")); ?>" class="dashboard-card">
            <h3>Manage Courses</h3>
            <p>View and reassign courses to lecturers.</p>
        </a>

        <a href="<?php echo e(with_context("account/profile.php")); ?>" class="dashboard-card">
            <h3>Profile Settings</h3>
            <p>Update your admin profile and change your password.</p>
        </a>

        <a href="<?php echo e(with_context("admin/audit_log.php")); ?>" class="dashboard-card">
            <h3>Audit Log</h3>
            <p>Review important actions recorded across the system.</p>
        </a>
    </div>

    <div class="admin-overview-panels">
        <section class="overview-panel">
            <div class="panel-heading">
                <h3>Recent Sessions</h3>
                <span><?php echo number_format($activeSessions); ?> active now</span>
            </div>

            <?php if (empty($recentSessions)) { ?>
                <p class="empty-state">No attendance sessions have been created yet.</p>
            <?php } else { ?>
                <div class="overview-list">
                    <?php foreach ($recentSessions as $session) {
                        $status = "Expired";
                        $statusClass = "status-expired";
                        if (!empty($session["closed_at"])) {
                            $status = "Closed";
                            $statusClass = "status-closed";
                        } elseif (strtotime($session["expires_at"]) >= time()) {
                            $status = "Active";
                            $statusClass = "status-active";
                        }
                    ?>
                        <div class="overview-list-item">
                            <div>
                                <strong><?php echo e($session["course_code"]); ?> - <?php echo e($session["course_title"]); ?></strong>
                                <span><?php echo e($session["lecturer_name"]); ?> · <?php echo e(date("M j, Y g:i A", strtotime($session["created_at"]))); ?></span>
                            </div>
                            <div class="overview-item-meta">
                                <span class="status-badge <?php echo e($statusClass); ?>"><?php echo e($status); ?></span>
                                <small><?php echo number_format((int) $session["marked_count"]); ?> marked</small>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
        </section>

        <section class="overview-panel">
            <div class="panel-heading">
                <h3>Latest Attendance</h3>
                <span><?php echo number_format($attendanceRecords); ?> total records</span>
            </div>

            <?php if (empty($recentAttendance)) { ?>
                <p class="empty-state">No student attendance has been submitted yet.</p>
            <?php } else { ?>
                <div class="overview-list">
                    <?php foreach ($recentAttendance as $record) {
                        $verified = ((int) $record["face_verified"] === 1 && (int) $record["location_verified"] === 1);
                    ?>
                        <div class="overview-list-item">
                            <div>
                                <strong><?php echo e($record["student_name"]); ?></strong>
                                <span><?php echo e($record["matric_no"] ?: "No matric number"); ?> · <?php echo e($record["course_code"]); ?></span>
                            </div>
                            <div class="overview-item-meta">
                                <span class="status-badge <?php echo $verified ? "status-active" : "status-expired"; ?>">
                                    <?php echo $verified ? "Verified" : "Needs Check"; ?>
                                </span>
                                <small><?php echo e(date("M j, g:i A", strtotime($record["marked_at"]))); ?></small>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
        </section>
    </div>
</div>

<?php render_context_script(); ?>
</body>
</html>
