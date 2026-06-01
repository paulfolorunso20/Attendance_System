<?php
require_once __DIR__ . "/../includes/bootstrap.php";
require_role("admin");

function audit_count($conn, $query)
{
    $result = mysqli_query($conn, $query);

    if (!$result) {
        return 0;
    }

    $row = mysqli_fetch_row($result);
    return (int) ($row[0] ?? 0);
}

$actionFilter = trim($_GET["action"] ?? "");
$roleFilter = trim($_GET["role"] ?? "");
$search = trim($_GET["search"] ?? "");

$conditions = [];
$types = "";
$params = [];

if ($actionFilter !== "") {
    $conditions[] = "action = ?";
    $types .= "s";
    $params[] = $actionFilter;
}

if ($roleFilter !== "") {
    $conditions[] = "user_role = ?";
    $types .= "s";
    $params[] = $roleFilter;
}

if ($search !== "") {
    $conditions[] = "(description LIKE ? OR action LIKE ? OR entity_type LIKE ?)";
    $types .= "sss";
    $searchValue = "%" . $search . "%";
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

$whereSql = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";
$query = "SELECT a.*, u.full_name, u.email
          FROM audit_logs a
          LEFT JOIN users u ON u.id = a.user_id
          $whereSql
          ORDER BY a.created_at DESC
          LIMIT 200";
$stmt = mysqli_prepare($conn, $query);

if ($types !== "") {
    $bindValues = [$types];
    foreach ($params as $index => $value) {
        $bindValues[] = &$params[$index];
    }
    call_user_func_array([$stmt, "bind_param"], $bindValues);
}

mysqli_stmt_execute($stmt);
$logs = mysqli_stmt_get_result($stmt);

$actions = mysqli_query($conn, "SELECT DISTINCT action FROM audit_logs ORDER BY action");
$totalLogs = audit_count($conn, "SELECT COUNT(*) FROM audit_logs");
$todayLogs = audit_count($conn, "SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = CURDATE()");
$failedAttempts = audit_count($conn, "SELECT COUNT(*) FROM audit_logs WHERE action LIKE '%failed%'");
$attendanceEvents = audit_count($conn, "SELECT COUNT(*) FROM audit_logs WHERE action LIKE 'attendance_%'");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Audit Log</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=professional-ui-5">
</head>
<body class="audit-log-page">

<div class="audit-log-shell">
    <div class="audit-hero">
        <div>
            <p class="section-kicker">Security & Activity</p>
            <h2>Audit Log</h2>
            <p class="welcome">Review important system activity, account changes, course updates, and attendance events.</p>
        </div>
        <a href="dashboard.php" class="button-link secondary-action">Back to Dashboard</a>
    </div>

    <div class="audit-stats-grid">
        <div class="audit-stat-card">
            <span>Total Logs</span>
            <strong><?php echo number_format($totalLogs); ?></strong>
            <small>All recorded system events</small>
        </div>
        <div class="audit-stat-card">
            <span>Today</span>
            <strong><?php echo number_format($todayLogs); ?></strong>
            <small>Events recorded today</small>
        </div>
        <div class="audit-stat-card">
            <span>Failed Checks</span>
            <strong><?php echo number_format($failedAttempts); ?></strong>
            <small>Failed login or verification events</small>
        </div>
        <div class="audit-stat-card">
            <span>Attendance Events</span>
            <strong><?php echo number_format($attendanceEvents); ?></strong>
            <small>Attendance marking activity</small>
        </div>
    </div>

    <form method="GET" class="audit-filter-panel">
        <div>
            <label>Action</label>
            <select name="action">
                <option value="">All Actions</option>
                <?php while ($row = mysqli_fetch_assoc($actions)) { ?>
                    <option value="<?php echo e($row["action"]); ?>" <?php echo $actionFilter === $row["action"] ? "selected" : ""; ?>>
                        <?php echo e(str_replace("_", " ", ucwords($row["action"], "_"))); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div>
            <label>Role</label>
            <select name="role">
                <option value="">All Roles</option>
                <option value="admin" <?php echo $roleFilter === "admin" ? "selected" : ""; ?>>Admin</option>
                <option value="lecturer" <?php echo $roleFilter === "lecturer" ? "selected" : ""; ?>>Lecturer</option>
                <option value="student" <?php echo $roleFilter === "student" ? "selected" : ""; ?>>Student</option>
            </select>
        </div>
        <div>
            <label>Search</label>
            <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search action or description">
        </div>
        <button type="submit">Filter Logs</button>
    </form>

    <div class="audit-table-card">
        <div class="panel-heading">
            <h3>Recent Activity</h3>
            <span>Showing latest 200 records</span>
        </div>
        <div class="table-wrap audit-table-wrap">
        <table border="1" cellpadding="10" cellspacing="0" width="100%">
            <tr>
                <th>Date & Time</th>
                <th>User</th>
                <th>Role</th>
                <th>Action</th>
                <th>Description</th>
                <th>Target</th>
                <th>IP Address</th>
            </tr>

            <?php if (mysqli_num_rows($logs) === 0) { ?>
                <tr>
                    <td colspan="7">No audit log records found.</td>
                </tr>
            <?php } ?>

            <?php while ($log = mysqli_fetch_assoc($logs)) { ?>
                <tr>
                    <td>
                        <strong><?php echo e(date("M j, Y", strtotime($log["created_at"]))); ?></strong><br>
                        <span><?php echo e(date("g:i A", strtotime($log["created_at"]))); ?></span>
                    </td>
                    <td>
                        <strong><?php echo e($log["full_name"] ?: "System / Unknown"); ?></strong><br>
                        <span><?php echo e($log["email"] ?: ""); ?></span>
                    </td>
                    <td><?php echo e(ucfirst($log["user_role"] ?: "N/A")); ?></td>
                    <td><span class="status-badge status-active"><?php echo e(str_replace("_", " ", ucwords($log["action"], "_"))); ?></span></td>
                    <td><?php echo e($log["description"]); ?></td>
                    <td><?php echo e(($log["entity_type"] ?: "N/A") . ($log["entity_id"] ? " #" . $log["entity_id"] : "")); ?></td>
                    <td><?php echo e($log["ip_address"] ?: "N/A"); ?></td>
                </tr>
            <?php } ?>
        </table>
        </div>
    </div>
</div>

</body>
</html>
