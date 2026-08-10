<?php
require_once __DIR__ . "/../includes/bootstrap.php";
require_role("lecturer");
require_valid_csrf();

$lecturer_id = current_user_id();
$flash = get_flash();

if (isset($_POST["close_session"])) {
    $sessionId = (int) $_POST["session_id"];
    $query = "UPDATE attendance_sessions SET closed_at = NOW() WHERE id = ? AND lecturer_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $sessionId, $lecturer_id);
    $saved = mysqli_stmt_execute($stmt);

    if ($saved) {
        audit_log($conn, "session_closed", "Lecturer closed an attendance session.", "attendance_session", $sessionId);
    }
    set_flash($saved ? "success" : "error", $saved ? "Session closed successfully." : "Could not close session.");
    redirect_with_context("lecturer/manage_sessions.php");
}

if (isset($_POST["extend_session"])) {
    $sessionId = (int) $_POST["session_id"];
    $minutes = max(1, min(180, (int) ($_POST["minutes"] ?? 10)));
    $query = "UPDATE attendance_sessions
              SET expires_at = DATE_ADD(GREATEST(expires_at, NOW()), INTERVAL ? MINUTE),
                  closed_at = NULL
              WHERE id = ? AND lecturer_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "iii", $minutes, $sessionId, $lecturer_id);
    $saved = mysqli_stmt_execute($stmt);

    if ($saved) {
        audit_log($conn, "session_extended", "Lecturer extended an attendance session by " . $minutes . " minutes.", "attendance_session", $sessionId);
    }
    set_flash($saved ? "success" : "error", $saved ? "Session extended successfully." : "Could not extend session.");
    redirect_with_context("lecturer/manage_sessions.php");
}

$query = "SELECT s.*, c.course_code, c.course_title, COUNT(ar.id) AS marked_count
          FROM attendance_sessions s
          JOIN courses c ON s.course_id = c.id
          LEFT JOIN attendance_records ar ON ar.session_id = s.id
          WHERE s.lecturer_id = ?
          GROUP BY s.id, c.course_code, c.course_title
          ORDER BY s.created_at DESC";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $lecturer_id);
mysqli_stmt_execute($stmt);
$sessions = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html>

<head>
    <title>Manage Sessions</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=professional-ui-5">
</head>

<body class="lecturer-sessions-page">

    <div class="dashboard-container wide-admin">
        <div class="lecturer-page-heading">
            <div>
                <p class="section-kicker">Lecturer Tool</p>
                <h2>Manage Attendance Sessions</h2>
                <p>Review active and previous QR sessions, extend time, or close a session.</p>
            </div>
            <a href="dashboard.php" class="button-link secondary-action">Back to Dashboard</a>
        </div>

        <?php if ($flash) { ?>
            <p class="alert alert-<?php echo e($flash["type"]); ?>"><?php echo e($flash["message"]); ?></p>
        <?php } ?>

        <div class="lecturer-session-list">
            <?php
            $hasSessions = false;
            while ($row = mysqli_fetch_assoc($sessions)) {
                $hasSessions = true;
                $isClosed = !empty($row["closed_at"]);
                $isExpired = strtotime($row["expires_at"]) < time();
                $status = $isClosed ? "Closed" : ($isExpired ? "Expired" : "Active");
                $statusClass = $isClosed ? "status-closed" : ($isExpired ? "status-expired" : "status-active");
                $qrLink = app_base_url() . "/attendance/mark_attendance.php?token=" . urlencode($row["session_token"]);
                ?>
                <article class="lecturer-session-card">
                    <div class="lecturer-session-header">
                        <div>
                            <span class="status-badge <?php echo e($statusClass); ?>"><?php echo e($status); ?></span>
                            <h3><?php echo e($row["course_code"] . " - " . $row["course_title"]); ?></h3>
                            <p>Session #<?php echo e($row["id"]); ?> created <?php echo e($row["created_at"]); ?></p>
                        </div>
                        <div class="session-count-card">
                            <strong><?php echo e($row["marked_count"]); ?></strong>
                            <span>Marked</span>
                        </div>
                    </div>

                    <div class="lecturer-session-body">
                        <div class="lecturer-session-qr">
                            <?php if (!$isClosed && !$isExpired) { ?>
                                <img src="../attendance/qr_code.php?data=<?php echo urlencode($qrLink); ?>" alt="Attendance QR code">
                            <?php } else { ?>
                                <div class="session-qr-placeholder"><?php echo dashboard_icon($isClosed ? "check" : "clock"); ?></div>
                            <?php } ?>
                            <a href="<?php echo e($qrLink); ?>" target="_blank" class="button-link secondary-action">Open QR Link</a>
                        </div>

                        <div class="lecturer-session-details">
                            <div>
                                <span>Expires</span>
                                <strong><?php echo e($row["expires_at"]); ?></strong>
                            </div>
                            <div>
                                <span>Allowed Radius</span>
                                <strong><?php echo e($row["radius_meters"]); ?>m</strong>
                            </div>
                            <div>
                                <span>QR Link</span>
                                <p class="link"><?php echo e($qrLink); ?></p>
                            </div>
                        </div>

                        <div class="lecturer-session-controls">
                            <form method="POST" class="inline-action-form">
                                <?php render_csrf_input(); ?>
                                <input type="hidden" name="session_id" value="<?php echo e($row["id"]); ?>">
                                <input type="number" name="minutes" min="1" max="180" value="10" aria-label="Minutes to extend">
                                <button type="submit" name="extend_session">Extend Session</button>
                            </form>

                            <?php if (!$isClosed) { ?>
                                <form method="POST" class="inline-action-form">
                                    <?php render_csrf_input(); ?>
                                    <input type="hidden" name="session_id" value="<?php echo e($row["id"]); ?>">
                                    <button type="submit" name="close_session" class="danger-button">End Session</button>
                                </form>
                            <?php } ?>
                        </div>
                    </div>
                </article>
            <?php } ?>

            <?php if (!$hasSessions) { ?>
                <div class="empty-state-card">
                    <h3>No attendance sessions yet</h3>
                    <p>Create a session to generate a QR code for student attendance.</p>
                    <a href="create_session.php" class="button-link">Create Session</a>
                </div>
            <?php } ?>
        </div>
    </div>

</body>

</html>
