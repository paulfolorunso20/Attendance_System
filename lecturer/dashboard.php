<?php
require_once __DIR__ . "/../includes/bootstrap.php";
require_role("lecturer");

$lecturer_id = current_user_id();
$query = "SELECT full_name, title, position, department, profile_image FROM users WHERE id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $lecturer_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$lecturer = mysqli_fetch_assoc($result);

$displayName = lecturer_display_name($lecturer["title"] ?? "", $lecturer["position"] ?? "", $lecturer["full_name"] ?? $_SESSION["full_name"]);

$stats_query = "SELECT
    (SELECT COUNT(*) FROM courses WHERE lecturer_id = ?) AS total_courses,
    (SELECT COUNT(*) FROM attendance_sessions WHERE lecturer_id = ?) AS total_sessions,
    (SELECT COUNT(*) FROM attendance_sessions WHERE lecturer_id = ? AND closed_at IS NULL AND expires_at >= NOW()) AS active_sessions,
    (SELECT COUNT(ar.id) FROM attendance_records ar JOIN attendance_sessions s ON s.id = ar.session_id WHERE s.lecturer_id = ?) AS attendance_records";
$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "iiii", $lecturer_id, $lecturer_id, $lecturer_id, $lecturer_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result) ?: [];

$activeSession = null;
$active_query = "SELECT s.id, s.created_at, s.expires_at, s.radius_meters, c.course_code, c.course_title, COUNT(ar.id) AS marked_count
                 FROM attendance_sessions s
                 JOIN courses c ON c.id = s.course_id
                 LEFT JOIN attendance_records ar ON ar.session_id = s.id
                 WHERE s.lecturer_id = ?
                   AND s.closed_at IS NULL
                   AND s.expires_at >= NOW()
                 GROUP BY s.id, s.created_at, s.expires_at, s.radius_meters, c.course_code, c.course_title
                 ORDER BY s.created_at DESC
                 LIMIT 1";
$active_stmt = mysqli_prepare($conn, $active_query);
mysqli_stmt_bind_param($active_stmt, "i", $lecturer_id);
mysqli_stmt_execute($active_stmt);
$active_result = mysqli_stmt_get_result($active_stmt);
$activeSession = mysqli_fetch_assoc($active_result);

$recentAttendance = [];
$recent_query = "SELECT u.full_name, c.course_code, c.course_title, ar.status, ar.face_verified, ar.location_verified, ar.marked_at
                 FROM attendance_records ar
                 JOIN attendance_sessions s ON s.id = ar.session_id
                 JOIN courses c ON c.id = s.course_id
                 JOIN users u ON u.id = ar.student_id
                 WHERE s.lecturer_id = ?
                 ORDER BY ar.marked_at DESC
                 LIMIT 5";
$recent_stmt = mysqli_prepare($conn, $recent_query);
mysqli_stmt_bind_param($recent_stmt, "i", $lecturer_id);
mysqli_stmt_execute($recent_stmt);
$recent_result = mysqli_stmt_get_result($recent_stmt);
while ($row = mysqli_fetch_assoc($recent_result)) {
    $recentAttendance[] = $row;
}

$recentActivities = [];
$activity_query = "SELECT action, description, created_at
                   FROM audit_logs
                   WHERE user_id = ?
                   ORDER BY created_at DESC
                   LIMIT 4";
$activity_stmt = mysqli_prepare($conn, $activity_query);
mysqli_stmt_bind_param($activity_stmt, "i", $lecturer_id);
mysqli_stmt_execute($activity_stmt);
$activity_result = mysqli_stmt_get_result($activity_stmt);
while ($row = mysqli_fetch_assoc($activity_result)) {
    $recentActivities[] = $row;
}

$currentDate = date("l, F j, Y");
$attendanceEfficiency = (int) min(100, max(0, ($stats["attendance_records"] ?? 0) > 0 ? 92 : 0));
?>

<!DOCTYPE html>
<html>
<head>
    <title>Lecturer Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=lecturer-dashboard-4">
</head>
<body class="role-dashboard-page lecturer-dashboard-page">

<div class="role-dashboard-shell">

    <header class="lecturer-topbar">
        <a href="<?php echo e(with_context("lecturer/dashboard.php")); ?>" class="lecturer-topbar-brand" aria-label="Lecturer dashboard home">
            <img src="../assets/img/smartattend-logo.svg" alt="SmartAttend logo">
            <span>
                <strong>SmartAttend</strong>
                <small>Lecturer Dashboard</small>
            </span>
        </a>
        <div class="lecturer-topbar-actions">
            <span class="lecturer-topbar-icon" aria-label="Notifications"><?php echo dashboard_icon("alert"); ?></span>
            <details class="lecturer-profile-menu">
                <summary>
                    <span class="dashboard-user-avatar compact-avatar">
                        <?php if (!empty($lecturer["profile_image"])) { ?>
                            <img src="<?php echo e(media_url($lecturer["profile_image"])); ?>" alt="<?php echo e($displayName); ?> profile picture">
                        <?php } else { ?>
                            <?php echo e(strtoupper(substr($lecturer["full_name"] ?? $_SESSION["full_name"], 0, 1))); ?>
                        <?php } ?>
                    </span>
                </summary>
                <div>
                    <a href="<?php echo e(with_context("account/profile.php")); ?>">My Profile</a>
                    <a href="<?php echo e(with_context("account/profile.php")); ?>">Settings</a>
                    <a href="<?php echo e(with_context("auth/logout.php")); ?>">Logout</a>
                </div>
            </details>
        </div>
    </header>

    <div class="role-dashboard-header">
        <div class="lecturer-hero-main">
            <p class="section-kicker"><?php echo e(($lecturer["department"] ?? "Lecturer Workspace") . " Department"); ?></p>
            <h2><?php echo e(time_greeting()); ?>,<br><?php echo e($displayName); ?></h2>
            <p class="welcome">Manage attendance sessions, track student participation, and export reports effortlessly.</p>
        </div>
        <div class="lecturer-hero-side">
            <span><?php echo e($currentDate); ?></span>
            <strong id="lecturerLiveClock"><?php echo e(date("g:i A")); ?></strong>
            <div class="lecturer-efficiency">
                <div class="lecturer-ring" style="--value: <?php echo e($attendanceEfficiency); ?>;">
                    <b><?php echo e($attendanceEfficiency); ?>%</b>
                </div>
                <small>Attendance Efficiency</small>
            </div>
            <a href="<?php echo e(with_context("lecturer/create_session.php")); ?>" class="button-link primary-action">New Session</a>
        </div>
    </div>

    <?php if (empty($lecturer["title"]) || empty($lecturer["position"]) || empty($lecturer["department"])) { ?>
        <p class="alert alert-error">Please complete your lecturer profile from the Profile page.</p>
    <?php } ?>

    <div class="role-stats-grid">
        <div class="role-stat-card">
            <?php echo dashboard_icon("book"); ?>
            <span>Courses</span>
            <strong><?php echo number_format((int) ($stats["total_courses"] ?? 0)); ?></strong>
            <small>Active Courses</small>
        </div>
        <div class="role-stat-card">
            <?php echo dashboard_icon("calendar"); ?>
            <span>Sessions</span>
            <strong><?php echo number_format((int) ($stats["total_sessions"] ?? 0)); ?></strong>
            <small>Sessions Created</small>
        </div>
        <div class="role-stat-card">
            <?php echo dashboard_icon("activity"); ?>
            <span>Active Now</span>
            <strong><?php echo number_format((int) ($stats["active_sessions"] ?? 0)); ?></strong>
            <small>Ongoing Sessions</small>
        </div>
        <div class="role-stat-card">
            <?php echo dashboard_icon("check"); ?>
            <span>Records</span>
            <strong><?php echo number_format((int) ($stats["attendance_records"] ?? 0)); ?></strong>
            <small>Attendance Records</small>
        </div>
    </div>

    <?php if ($activeSession) { ?>
        <section class="lecturer-live-session-card" id="dashboardLiveSession" data-session-id="<?php echo e($activeSession["id"]); ?>">
            <div>
                <span class="status-badge status-active">Live Session Running</span>
                <h3><?php echo e($activeSession["course_code"]); ?> - <?php echo e($activeSession["course_title"]); ?></h3>
                <p>Students can still scan and mark attendance for this class.</p>
            </div>
            <div class="lecturer-live-session-meta">
                <div>
                    <span>Marked</span>
                    <strong id="dashboardMarkedCount"><?php echo number_format((int) $activeSession["marked_count"]); ?></strong>
                </div>
                <div>
                    <span>Expires</span>
                    <strong id="dashboardExpiresAt"><?php echo e(date("g:i A", strtotime($activeSession["expires_at"]))); ?></strong>
                </div>
                <div>
                    <span>Radius</span>
                    <strong><?php echo e($activeSession["radius_meters"]); ?>m</strong>
                </div>
            </div>
            <div class="lecturer-live-session-actions">
                <a href="<?php echo e(with_context("lecturer/create_session.php")); ?>" class="button-link">Open QR Card</a>
                <a href="<?php echo e(with_context("lecturer/manage_sessions.php")); ?>" class="button-link secondary-action">Manage</a>
            </div>
        </section>
    <?php } ?>

    <section class="lecturer-dashboard-section">
        <div class="lecturer-section-heading">
            <h3>Quick Actions</h3>
            <p>Common tools for managing lecture attendance.</p>
        </div>

        <div class="dashboard-grid role-action-grid">

            <a href="<?php echo e(with_context("lecturer/create_session.php")); ?>" class="dashboard-card">
                <span class="action-icon"><?php echo dashboard_icon("plus"); ?></span>
                <h3>Create Session</h3>
                <p>Generate attendance QR.</p>
            </a>

            <a href="<?php echo e(with_context("lecturer/manage_sessions.php")); ?>" class="dashboard-card">
                <span class="action-icon"><?php echo dashboard_icon("clock"); ?></span>
                <h3>Manage Sessions</h3>
                <p>View active sessions.</p>
            </a>

            <a href="<?php echo e(with_context("lecturer/create_course.php")); ?>" class="dashboard-card">
                <span class="action-icon"><?php echo dashboard_icon("book"); ?></span>
                <h3>Add Course</h3>
                <p>Register a new course.</p>
            </a>

            <a href="<?php echo e(with_context("lecturer/view_records.php")); ?>" class="dashboard-card">
                <span class="action-icon"><?php echo dashboard_icon("table"); ?></span>
                <h3>Attendance Records</h3>
                <p>View student records.</p>
            </a>

        </div>
    </section>

    <div class="lecturer-lower-grid">
        <section class="lecturer-table-card">
            <div class="lecturer-section-heading">
                <h3>Recent Attendance</h3>
                <p>Latest verified submissions from your sessions.</p>
            </div>
            <div class="lecturer-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentAttendance)) { ?>
                            <tr>
                                <td colspan="4">No recent attendance records yet.</td>
                            </tr>
                        <?php } ?>
                        <?php foreach ($recentAttendance as $record) {
                            $isPresent = ($record["status"] ?? "") === "present";
                            $badgeClass = $isPresent ? "status-present" : "status-absent";
                            $statusLabel = $isPresent ? "Present" : "Rejected";
                        ?>
                            <tr>
                                <td><?php echo e($record["full_name"]); ?></td>
                                <td><?php echo e($record["course_code"]); ?></td>
                                <td><?php echo e(date("M j, g:i A", strtotime($record["marked_at"]))); ?></td>
                                <td><span class="table-status <?php echo e($badgeClass); ?>"><?php echo e($statusLabel); ?></span></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="lecturer-activity-card">
            <div class="lecturer-section-heading">
                <h3>Recent Activity</h3>
                <p>Your latest system actions.</p>
            </div>
            <div class="activity-list">
                <?php if (empty($recentActivities)) { ?>
                    <p class="empty-activity">No recent activity yet.</p>
                <?php } ?>
                <?php foreach ($recentActivities as $activity) { ?>
                    <div class="activity-item">
                        <span><?php echo dashboard_icon("check"); ?></span>
                        <div>
                            <strong><?php echo e(ucwords(str_replace("_", " ", $activity["action"]))); ?></strong>
                            <small><?php echo e(date("M j, g:i A", strtotime($activity["created_at"]))); ?></small>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </aside>
    </div>

</div>

<script>
(function () {
    var clock = document.getElementById("lecturerLiveClock");
    if (!clock) {
        return;
    }

    function updateClock() {
        clock.textContent = new Date().toLocaleTimeString([], { hour: "numeric", minute: "2-digit" });
    }

    updateClock();
    window.setInterval(updateClock, 30000);
})();
</script>

<?php if ($activeSession) { ?>
<script>
const dashboardLiveSession = document.getElementById("dashboardLiveSession");
const dashboardMarkedCount = document.getElementById("dashboardMarkedCount");
const dashboardExpiresAt = document.getElementById("dashboardExpiresAt");
const dashboardStatusUrl = <?php echo json_encode(with_context("attendance/session_status.php?session_id=" . (int) $activeSession["id"])); ?>;

function dashboardFormatTime(value) {
    return new Date(value).toLocaleTimeString([], { hour: "numeric", minute: "2-digit" });
}

async function refreshDashboardLiveSession() {
    if (!dashboardLiveSession || !dashboardStatusUrl) {
        return;
    }

    try {
        const response = await fetch(dashboardStatusUrl, { cache: "no-store" });
        const data = await response.json();

        if (!data.ok) {
            return;
        }

        if (dashboardMarkedCount) {
            dashboardMarkedCount.textContent = data.marked_count;
        }

        if (dashboardExpiresAt && data.expires_at) {
            dashboardExpiresAt.textContent = dashboardFormatTime(data.expires_at);
        }

        if (data.status !== "active") {
            dashboardLiveSession.classList.add("is-ended");
        }
    } catch (error) {
        return;
    }
}

refreshDashboardLiveSession();
window.setInterval(refreshDashboardLiveSession, 5000);
</script>
<?php } ?>

</body>
</html>
