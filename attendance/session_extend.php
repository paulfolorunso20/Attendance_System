<?php
require_once __DIR__ . "/../includes/bootstrap.php";
require_role("lecturer");
require_valid_csrf();

$lecturer_id = current_user_id();
$session_id = (int) ($_POST["session_id"] ?? 0);
$minutes = max(1, min(180, (int) ($_POST["minutes"] ?? 10)));

header("Content-Type: application/json");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

if ($session_id <= 0) {
    echo json_encode(["ok" => false, "error" => "Invalid session."]);
    exit();
}

$query = "UPDATE attendance_sessions
          SET expires_at = DATE_ADD(GREATEST(expires_at, NOW()), INTERVAL ? MINUTE),
              closed_at = NULL
          WHERE id = ? AND lecturer_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "iii", $minutes, $session_id, $lecturer_id);
$saved = mysqli_stmt_execute($stmt);

if (!$saved) {
    echo json_encode(["ok" => false, "error" => "Could not extend session."]);
    exit();
}

audit_log($conn, "session_extended", "Lecturer extended an attendance session by " . $minutes . " minutes.", "attendance_session", $session_id);

$select = "SELECT expires_at FROM attendance_sessions WHERE id = ? AND lecturer_id = ? LIMIT 1";
$select_stmt = mysqli_prepare($conn, $select);
mysqli_stmt_bind_param($select_stmt, "ii", $session_id, $lecturer_id);
mysqli_stmt_execute($select_stmt);
$result = mysqli_stmt_get_result($select_stmt);
$session = mysqli_fetch_assoc($result);

echo json_encode([
    "ok" => true,
    "expires_at" => date("c", strtotime($session["expires_at"])),
    "message" => "Session extended by " . $minutes . " minutes.",
]);
