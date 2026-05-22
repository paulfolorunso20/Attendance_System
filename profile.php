<?php
session_start();
include "config/db.php";
include "includes/functions.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = current_user_id();
$role = $_SESSION["role"] ?? "";
$error = null;
$success = null;

$query = "SELECT full_name, title, position, matric_no, department, email, password, role FROM users WHERE id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

if (isset($_POST["save_profile"])) {
    $fullName = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $matricNo = trim($_POST["matric_no"] ?? "");
    $department = trim($_POST["department"] ?? "");
    $title = trim($_POST["title"] ?? "");
    $position = trim($_POST["position"] ?? "");

    if ($fullName === "" || $email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid name and email address.";
    } elseif ($role === "student" && $matricNo === "") {
        $error = "Please enter your matric number.";
    } elseif (($role === "student" || $role === "lecturer") && $department === "") {
        $error = "Please enter your department.";
    } elseif ($role === "lecturer" && !in_array($title, ["Mr", "Mrs", "Miss"], true)) {
        $error = "Please select a valid title.";
    } elseif ($role === "lecturer" && !in_array($position, ["Prof", "HOD", "Dr", "Normal"], true)) {
        $error = "Please select a valid position.";
    } else {
        $matricValue = $role === "student" ? $matricNo : null;
        $departmentValue = ($role === "student" || $role === "lecturer") ? $department : null;
        $titleValue = $role === "lecturer" ? $title : null;
        $positionValue = $role === "lecturer" ? $position : null;

        $update = "UPDATE users SET full_name = ?, email = ?, matric_no = ?, department = ?, title = ?, position = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update);
        mysqli_stmt_bind_param($update_stmt, "ssssssi", $fullName, $email, $matricValue, $departmentValue, $titleValue, $positionValue, $user_id);

        try {
            $saved = mysqli_stmt_execute($update_stmt);
        } catch (mysqli_sql_exception $exception) {
            $saved = false;
        }

        if ($saved) {
            $_SESSION["full_name"] = $fullName;
            $_SESSION["title"] = $titleValue ?? "";
            $_SESSION["position"] = $positionValue ?? "";
            $success = "Profile updated successfully.";
            audit_log($conn, "profile_updated", "Profile details updated.", "user", $user_id);
            $user["full_name"] = $fullName;
            $user["email"] = $email;
            $user["matric_no"] = $matricValue;
            $user["department"] = $departmentValue;
            $user["title"] = $titleValue;
            $user["position"] = $positionValue;
        } else {
            $error = "Could not update profile. Email or matric number may already be used.";
        }
    }
}

if (isset($_POST["change_password"])) {
    $currentPassword = trim($_POST["current_password"] ?? "");
    $newPassword = trim($_POST["new_password"] ?? "");
    $confirmPassword = trim($_POST["confirm_password"] ?? "");
    $storedPassword = $user["password"];
    $currentOk = password_verify($currentPassword, $storedPassword) || hash_equals($storedPassword, $currentPassword);

    if (!$currentOk) {
        $error = "Current password is incorrect.";
    } elseif (strlen($newPassword) < 6) {
        $error = "New password must be at least 6 characters.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "New password and confirmation do not match.";
    } else {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $update = "UPDATE users SET password = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update);
        mysqli_stmt_bind_param($update_stmt, "si", $passwordHash, $user_id);

        if (mysqli_stmt_execute($update_stmt)) {
            $success = "Password changed successfully.";
            $user["password"] = $passwordHash;
            audit_log($conn, "password_changed", "Account password changed.", "user", $user_id);
        } else {
            $error = "Could not change password.";
        }
    }
}

$backLink = "login.php";
if ($role === "student") {
    $backLink = "student_dashboard.php";
} elseif ($role === "lecturer") {
    $backLink = "lecturer_dashboard.php";
} elseif ($role === "admin") {
    $backLink = "admin_dashboard.php";
}

$displayRole = ucfirst($role);
$displayName = $user["full_name"];

if ($role === "lecturer") {
    $displayName = lecturer_display_name($user["title"] ?? "", $user["position"] ?? "", $user["full_name"]);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Profile Management</title>
    <link rel="stylesheet" href="assets/css/style.css?v=ui-polish-1">
</head>
<body class="profile-page">

<div class="profile-shell">
    <div class="profile-topbar">
        <a href="<?php echo e($backLink); ?>">Back to Dashboard</a>
        <span><?php echo e(date("l, F j, Y")); ?></span>
    </div>

    <div class="profile-hero">
        <div class="profile-avatar"><?php echo e(strtoupper(substr($user["full_name"], 0, 1))); ?></div>
        <div class="profile-hero-copy">
            <p class="profile-kicker">Account Settings</p>
            <h2><?php echo e($displayName); ?></h2>
            <p>Manage your account details, identity information, and password security.</p>
        </div>
    </div>

    <?php if ($error) { ?>
        <p class="alert alert-error"><?php echo e($error); ?></p>
    <?php } ?>

    <?php if ($success) { ?>
        <p class="alert alert-success"><?php echo e($success); ?></p>
    <?php } ?>

    <div class="profile-layout">
        <aside class="profile-summary-card">
            <div class="profile-summary-row">
                <span>Role</span>
                <strong><?php echo e($displayRole); ?></strong>
            </div>
            <div class="profile-summary-row">
                <span>Email</span>
                <strong><?php echo e($user["email"]); ?></strong>
            </div>
            <?php if ($role === "student" && !empty($user["matric_no"])) { ?>
            <div class="profile-summary-row">
                <span>Matric Number</span>
                <strong><?php echo e($user["matric_no"]); ?></strong>
            </div>
            <?php } ?>
            <?php if ($role === "student" && !empty($user["department"])) { ?>
            <div class="profile-summary-row">
                <span>Department</span>
                <strong><?php echo e($user["department"]); ?></strong>
            </div>
            <?php } ?>
            <?php if ($role === "lecturer") { ?>
            <div class="profile-summary-row">
                <span>Position</span>
                <strong><?php echo e($user["position"] ?: "Not set"); ?></strong>
            </div>
            <?php } ?>
            <?php if (($role === "student" || $role === "lecturer") && !empty($user["department"])) { ?>
            <div class="profile-summary-row">
                <span>Department</span>
                <strong><?php echo e($user["department"]); ?></strong>
            </div>
            <?php } ?>
            <div class="profile-summary-note">
                Changes made here update what appears across dashboards, reports, and attendance records.
            </div>
        </aside>

        <div class="profile-settings-stack">
        <form method="POST" class="settings-panel">
            <div class="settings-heading">
                <div>
                    <h3>Personal Information</h3>
                    <p>Keep your visible account details accurate.</p>
                </div>
            </div>

            <?php if ($role === "lecturer") { ?>
                <div class="field-row">
                    <div>
                        <label>Title</label>
                        <select name="title" required>
                            <option value="">Select Title</option>
                            <option value="Mr" <?php echo ($user["title"] === "Mr") ? "selected" : ""; ?>>Mr</option>
                            <option value="Mrs" <?php echo ($user["title"] === "Mrs") ? "selected" : ""; ?>>Mrs</option>
                            <option value="Miss" <?php echo ($user["title"] === "Miss") ? "selected" : ""; ?>>Miss</option>
                        </select>
                    </div>

                    <div>
                        <label>Position</label>
                        <select name="position" required>
                            <option value="">Select Position</option>
                            <option value="Prof" <?php echo ($user["position"] === "Prof") ? "selected" : ""; ?>>Prof</option>
                            <option value="HOD" <?php echo ($user["position"] === "HOD") ? "selected" : ""; ?>>HOD</option>
                            <option value="Dr" <?php echo ($user["position"] === "Dr") ? "selected" : ""; ?>>Dr</option>
                            <option value="Normal" <?php echo ($user["position"] === "Normal") ? "selected" : ""; ?>>Normal Lecturer</option>
                        </select>
                    </div>
                </div>
                <label>Department</label>
                <select name="department" required>
                    <option value="">Select Department</option>
                    <?php render_department_options($user["department"]); ?>
                </select>
            <?php } ?>

            <label>Full Name</label>
            <input type="text" name="full_name" value="<?php echo e($user["full_name"]); ?>" required>

            <?php if ($role === "student") { ?>
                <label>Matric Number</label>
                <input type="text" name="matric_no" value="<?php echo e($user["matric_no"]); ?>" required>

                <label>Department</label>
                <select name="department" required>
                    <option value="">Select Department</option>
                    <?php render_department_options($user["department"]); ?>
                </select>
            <?php } ?>

            <label>Email Address</label>
            <input type="email" name="email" value="<?php echo e($user["email"]); ?>" required>

            <button type="submit" name="save_profile">Save Profile</button>
        </form>

        <form method="POST" class="settings-panel">
            <div class="settings-heading">
                <div>
                    <h3>Password Security</h3>
                    <p>Use a password that is hard for others to guess.</p>
                </div>
            </div>

            <label>Current Password</label>
            <input type="password" name="current_password" required>

            <label>New Password</label>
            <input type="password" name="new_password" required>

            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" required>

            <button type="submit" name="change_password">Change Password</button>
        </form>
        </div>
    </div>
</div>

</body>
</html>
