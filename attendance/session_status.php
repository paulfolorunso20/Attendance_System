<?php
session_start();
include __DIR__ . "/../config/db.php";
include __DIR__ . "/../includes/functions.php";

require_role("lecturer");

$lecturer_id = current_user_id();
$session_id = (int) ($_GET["session_id"] ?? 0);

header("Content-Type: application/json");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

if ($session_id <= 0) {
    echo json_encode(["ok" => false, "error" => "Invalid session."]);
    exit();
}

$query = "SELECT s.id, s.expires_at, s.closed_at, COUNT(ar.id) AS marked_count
          FROM attendance_sessions s
          LEFT JOIN attendance_records ar ON ar.session_id = s.id
          WHERE s.id = ? AND s.lecturer_id = ?
          GROUP BY s.id, s.expires_at, s.closed_at
          LIMIT 1";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ii", $session_id, $lecturer_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$session = mysqli_fetch_assoc($result);

if (!$session) {
    echo json_encode(["ok" => false, "error" => "Session not found."]);
    exit();
}

$isClosed = !empty($session["closed_at"]);
$isExpired = strtotime($session["expires_at"]) < time();

echo json_encode([
    "ok" => true,
    "marked_count" => (int) $session["marked_count"],
    "status" => $isClosed ? "closed" : ($isExpired ? "expired" : "active"),
    "expires_at" => date("c", strtotime($session["expires_at"])),
]);
