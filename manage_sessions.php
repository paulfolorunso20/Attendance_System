<?php
session_start();
include "config/db.php";
include "includes/functions.php";

require_role("lecturer");

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
    redirect_with_context("manage_sessions.php");
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
    redirect_with_context("manage_sessions.php");
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
    <link rel="stylesheet" href="assets/css/style.css?v=session-live-count-1">
</head>
<body>

<div class="dashboard-container wide-admin">
    <h2>Manage Attendance Sessions</h2>

    <?php if ($flash) { ?>
        <p class="alert alert-<?php echo e($flash["type"]); ?>"><?php echo e($flash["message"]); ?></p>
    <?php } ?>

    <div class="table-wrap">
        <table border="1" cellpadding="10" cellspacing="0" width="100%">
            <tr>
                <th>Course</th>
                <th>Created</th>
                <th>Expires</th>
                <th>Status</th>
                <th>Marked</th>
                <th>QR Link</th>
                <th>Actions</th>
            </tr>

            <?php while ($row = mysqli_fetch_assoc($sessions)) {
                $isClosed = !empty($row["closed_at"]);
                $isExpired = strtotime($row["expires_at"]) < time();
                $status = $isClosed ? "Closed" : ($isExpired ? "Expired" : "Active");
                $statusClass = $isClosed ? "status-closed" : ($isExpired ? "status-expired" : "status-active");
                $qrLink = app_base_url() . "/mark_attendance.php?token=" . urlencode($row["session_token"]);
            ?>
            <tr>
                <td><?php echo e($row["course_code"] . " - " . $row["course_title"]); ?></td>
                <td>
                    <strong>#<?php echo e($row["id"]); ?></strong><br>
                    <?php echo e($row["created_at"]); ?>
                </td>
                <td><?php echo e($row["expires_at"]); ?></td>
                <td><span class="status-badge <?php echo e($statusClass); ?>"><?php echo e($status); ?></span></td>
                <td><span class="marked-pill"><?php echo e($row["marked_count"]); ?> marked</span></td>
                <td>
                    <?php if (!$isClosed && !$isExpired) { ?>
                        <div class="session-mini-qr">
                            <img src="qr_code.php?data=<?php echo urlencode($qrLink); ?>" alt="Attendance QR code">
                        </div>
                    <?php } ?>
                    <a href="<?php echo e($qrLink); ?>" target="_blank">Open</a>
                    <p class="link"><?php echo e($qrLink); ?></p>
                </td>
                <td>
                    <form method="POST" class="inline-action-form">
                        <input type="hidden" name="session_id" value="<?php echo e($row["id"]); ?>">
                        <input type="number" name="minutes" min="1" max="180" value="10" aria-label="Minutes to extend">
                        <button type="submit" name="extend_session">Extend</button>
                    </form>

                    <?php if (!$isClosed) { ?>
                    <form method="POST" class="inline-action-form">
                        <input type="hidden" name="session_id" value="<?php echo e($row["id"]); ?>">
                        <button type="submit" name="close_session" class="danger-button">Close</button>
                    </form>
                    <?php } ?>
                </td>
            </tr>
            <?php } ?>
        </table>
    </div>

    <br>
    <a href="lecturer_dashboard.php">Back to Dashboard</a>
</div>

</body>
</html>
