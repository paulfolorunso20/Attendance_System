<?php
require_once __DIR__ . "/../includes/bootstrap.php";

$error = null;
$success = null;
$selectedRole = $_POST["role"] ?? "student";

if (isset($_POST["reset_password"])) {
    $role = trim($_POST["role"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $matricNo = trim($_POST["matric_no"] ?? "");
    $newPassword = trim($_POST["new_password"] ?? "");
    $confirmPassword = trim($_POST["confirm_password"] ?? "");

    if (!in_array($role, ["student", "lecturer"], true)) {
        $error = "Please select a valid account type.";
    } elseif ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter the email address on your account.";
    } elseif ($role === "student" && $matricNo === "") {
        $error = "Students must enter their matric number.";
    } elseif ($role === "student" && !is_valid_matric_no($matricNo)) {
        $error = "Matric number must follow this format: 2022/42335.";
    } elseif (strlen($newPassword) < 6) {
        $error = "New password must be at least 6 characters.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "The new passwords do not match.";
    } else {
        if ($role === "student") {
            $query = "SELECT id, full_name, role FROM users WHERE role = 'student' AND email = ? AND matric_no = ? LIMIT 1";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "ss", $email, $matricNo);
        } else {
            $query = "SELECT id, full_name, role FROM users WHERE role = ? AND email = ? LIMIT 1";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "ss", $role, $email);
        }

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) !== 1) {
            audit_log($conn, "password_reset_failed", "Password reset failed for " . $email, "user", null, 0, $role);
            $error = "No matching account was found. Please check the details and try again.";
        } else {
            $user = mysqli_fetch_assoc($result);
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $update = "UPDATE users SET password = ? WHERE id = ?";
            $updateStmt = mysqli_prepare($conn, $update);
            mysqli_stmt_bind_param($updateStmt, "si", $passwordHash, $user["id"]);

            if (mysqli_stmt_execute($updateStmt)) {
                audit_log($conn, "password_reset", "Password was reset for " . $user["full_name"], "user", (int) $user["id"], (int) $user["id"], $user["role"]);
                $success = "Password reset successful. You can now login with your new password.";
                $selectedRole = $role;
            } else {
                $error = "Could not reset password. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=professional-ui-5">
</head>
<body class="auth-page">

<div class="login-container">
    <div class="auth-brand-panel">
        <div class="brand-mark">SA</div>
        <p class="section-kicker">Account Recovery</p>
        <h1>Reset your SmartAttend password</h1>
        <p>Recover access using the details already attached to your account.</p>
        <div class="auth-feature-list">
            <span>Student</span>
            <span>Lecturer</span>
        </div>
    </div>
    <div class="container">
        <div class="login-header">
            <p class="section-kicker">Forgot Password</p>
            <h2>Create a new password</h2>
            <p>Students use matric no. plus email. Lecturers use email.</p>
        </div>

        <?php if ($error) { ?>
            <p class="alert alert-error"><?php echo e($error); ?></p>
        <?php } ?>

        <?php if ($success) { ?>
            <p class="alert alert-success"><?php echo e($success); ?></p>
        <?php } ?>

        <form method="POST">
            <select name="role" id="reset-role" required>
                <option value="student" <?php echo $selectedRole === "student" ? "selected" : ""; ?>>Student</option>
                <option value="lecturer" <?php echo $selectedRole === "lecturer" ? "selected" : ""; ?>>Lecturer</option>
            </select>
            <input type="email" name="email" placeholder="Account email address" required>
            <input type="text" name="matric_no" id="reset-matric" placeholder="Student matric no. e.g. 2022/42335" pattern="\d{4}/\d{5}" maxlength="10" inputmode="numeric" data-matric-format title="Use four digits, slash, then five digits. Example: 2022/42335">
            <input type="password" name="new_password" placeholder="New password" required>
            <input type="password" name="confirm_password" placeholder="Confirm new password" required>
            <button type="submit" name="reset_password">Reset Password</button>
        </form>

        <p class="form-footer">Remembered your password? <a href="login.php">Back to login</a></p>
    </div>
</div>

<script>
const roleSelect = document.getElementById("reset-role");
const matricInput = document.getElementById("reset-matric");

function syncMatricField() {
    const isStudent = roleSelect.value === "student";
    matricInput.style.display = isStudent ? "block" : "none";
    matricInput.required = isStudent;
}

roleSelect.addEventListener("change", syncMatricField);
syncMatricField();
</script>

<script src="../assets/js/password-toggle.js?v=1"></script>
<script src="../assets/js/matric-format.js?v=1"></script>
</body>
</html>
