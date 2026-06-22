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
?>

<!DOCTYPE html>
<html>
<head>
    <title>Lecturer Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=lecturer-dashboard-3">
</head>
<body class="role-dashboard-page lecturer-dashboard-page">

<div class="role-dashboard-shell">

    <div class="role-dashboard-header">
        <div class="dashboard-user-avatar">
            <?php if (!empty($lecturer["profile_image"])) { ?>
                <img src="<?php echo e(media_url($lecturer["profile_image"])); ?>" alt="<?php echo e($displayName); ?> profile picture">
            <?php } else { ?>
                <?php echo e(strtoupper(substr($lecturer["full_name"] ?? $_SESSION["full_name"], 0, 1))); ?>
            <?php } ?>
        </div>
        <div>
            <p class="section-kicker"><?php echo e($lecturer["department"] ?? "Lecturer Workspace"); ?></p>
            <h2><?php echo e(time_greeting()); ?>, <?php echo e($displayName); ?></h2>
            <p class="welcome">Create sessions, manage QR codes, and review verified student attendance from one place.</p>
        </div>
        <div class="role-header-actions">
            <a href="<?php echo e(with_context("account/profile.php")); ?>" class="button-link secondary-action">Profile</a>
            <a href="<?php echo e(with_context("auth/logout.php")); ?>" class="button-link danger-action">Logout</a>
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
            <small>Assigned to your lecturer account</small>
        </div>
        <div class="role-stat-card">
            <?php echo dashboard_icon("calendar"); ?>
            <span>Sessions</span>
            <strong><?php echo number_format((int) ($stats["total_sessions"] ?? 0)); ?></strong>
            <small>Total attendance sessions created</small>
        </div>
        <div class="role-stat-card">
            <?php echo dashboard_icon("activity"); ?>
            <span>Active Now</span>
            <strong><?php echo number_format((int) ($stats["active_sessions"] ?? 0)); ?></strong>
            <small>Open sessions students can still mark</small>
        </div>
        <div class="role-stat-card">
            <?php echo dashboard_icon("check"); ?>
            <span>Records</span>
            <strong><?php echo number_format((int) ($stats["attendance_records"] ?? 0)); ?></strong>
            <small>Student attendance submissions</small>
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

    <div class="dashboard-grid role-action-grid">

        <a href="create_session.php" class="dashboard-card">
            <h3>Create Attendance Session</h3>
            <p>Generate QR code for a lecture attendance session.</p>
        </a>

        <a href="manage_sessions.php" class="dashboard-card">
            <h3>Manage Sessions</h3>
            <p>View active sessions, extend time, reopen QR links, or close attendance.</p>
        </a>

        <a href="create_course.php" class="dashboard-card">
            <h3>Add Course</h3>
            <p>Register a course under your lecturer account.</p>
        </a>

        <a href="view_records.php" class="dashboard-card">
            <h3>View Attendance Records</h3>
            <p>View and manage student attendance records.</p>
        </a>

    </div>

</div>

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
