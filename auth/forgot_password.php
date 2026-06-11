<?php
require_once __DIR__ . "/../includes/bootstrap.php";

$error = null;
$success = null;
$requestedWorkflow = $_GET["recover_as"] ?? $_POST["recover_as"] ?? "";
$requestedWorkflow = $requestedWorkflow === "staff" ? "staff" : ($requestedWorkflow === "student" ? "student" : "");
$selectedWorkflow = $requestedWorkflow ?: ($_SESSION["password_reset_workflow"] ?? "");
$selectedRole = $_SESSION["password_reset_role"] ?? ($selectedWorkflow === "student" ? "student" : "lecturer");
$step = "identify";

if (!empty($_SESSION["password_reset_verified_user_id"])) {
    $step = "reset";
} elseif (!empty($_SESSION["password_reset_user_id"])) {
    $step = "verify";
}

function clear_password_reset_session()
{
    unset(
        $_SESSION["password_reset_user_id"],
        $_SESSION["password_reset_email"],
        $_SESSION["password_reset_name"],
        $_SESSION["password_reset_matric_no"],
        $_SESSION["password_reset_workflow"],
        $_SESSION["password_reset_role"],
        $_SESSION["password_reset_verified_user_id"]
    );
}

if (isset($_POST["request_code"])) {
    clear_password_reset_session();

    $workflow = trim($_POST["recover_as"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $matricNo = trim($_POST["matric_no"] ?? "");
    $selectedWorkflow = $workflow === "staff" ? "staff" : ($workflow === "student" ? "student" : "");
    $selectedRole = $selectedWorkflow === "student" ? "student" : "lecturer";

    if (!in_array($selectedWorkflow, ["student", "staff"], true)) {
        $error = "Please select Student Login or Lecturer Portal first.";
    } elseif ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter the email address on your account.";
    } elseif ($selectedWorkflow === "student" && $matricNo === "") {
        $error = "Students must enter their matric no.";
    } elseif ($selectedWorkflow === "student" && !is_valid_matric_no($matricNo)) {
        $error = "Matric no. must follow this format: 2022/42335.";
    } elseif (!mail_configured()) {
        $error = "Email recovery is not configured yet. Please contact the system admin.";
    } else {
        if ($selectedWorkflow === "student") {
            $query = "SELECT id, full_name, email, role FROM users WHERE role = 'student' AND email = ? AND matric_no = ? LIMIT 1";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "ss", $email, $matricNo);
        } else {
            $query = "SELECT id, full_name, email, role FROM users WHERE role IN ('lecturer', 'admin') AND email = ? LIMIT 1";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "s", $email);
        }

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) !== 1) {
            audit_log($conn, "password_reset_code_failed", "Password recovery account lookup failed for " . $email, "user", null, 0, $selectedRole);
            $error = "No matching account was found. Please check the details and try again.";
        } else {
            $user = mysqli_fetch_assoc($result);
            $code = (string) random_int(100000, 999999);
            $codeHash = password_hash($code, PASSWORD_DEFAULT);
            $expiresAt = date("Y-m-d H:i:s", strtotime("+10 minutes"));

            $delete = mysqli_prepare($conn, "DELETE FROM password_reset_codes WHERE user_id = ? AND used_at IS NULL");
            mysqli_stmt_bind_param($delete, "i", $user["id"]);
            mysqli_stmt_execute($delete);

            $insert = mysqli_prepare($conn, "INSERT INTO password_reset_codes (user_id, email, code_hash, expires_at) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($insert, "isss", $user["id"], $user["email"], $codeHash, $expiresAt);

            if (!mysqli_stmt_execute($insert)) {
                $error = "Could not create a recovery code. Please try again.";
            } elseif (!send_password_reset_code_email($user["email"], $user["full_name"], $code)) {
                $error = "Could not send the recovery email. Please try again later.";
            } else {
                $_SESSION["password_reset_user_id"] = (int) $user["id"];
                $_SESSION["password_reset_email"] = $user["email"];
                $_SESSION["password_reset_name"] = $user["full_name"];
                $_SESSION["password_reset_matric_no"] = $matricNo;
                $_SESSION["password_reset_workflow"] = $selectedWorkflow;
                $_SESSION["password_reset_role"] = $user["role"];
                $selectedRole = $user["role"];
                $step = "verify";
                $success = "A 6-digit recovery code has been sent to your registered email.";
                audit_log($conn, "password_reset_code_sent", "Password recovery code sent to " . $user["email"], "user", (int) $user["id"], (int) $user["id"], $user["role"]);
            }
        }
    }
}

if (isset($_POST["verify_code"])) {
    $code = trim($_POST["recovery_code"] ?? "");
    $userId = (int) ($_SESSION["password_reset_user_id"] ?? 0);
    $selectedWorkflow = $_SESSION["password_reset_workflow"] ?? $selectedWorkflow;
    $selectedRole = $_SESSION["password_reset_role"] ?? $selectedRole;

    if ($userId <= 0) {
        $error = "Please request a new recovery code.";
        $step = "identify";
    } elseif (!preg_match('/^\d{6}$/', $code)) {
        $error = "Enter the 6-digit recovery code sent to your email.";
        $step = "verify";
    } else {
        $query = "SELECT id, code_hash FROM password_reset_codes
            WHERE user_id = ? AND used_at IS NULL AND expires_at >= NOW()
            ORDER BY id DESC LIMIT 1";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $resetCode = mysqli_fetch_assoc($result);

        if (!$resetCode || !password_verify($code, $resetCode["code_hash"])) {
            audit_log($conn, "password_reset_code_invalid", "Invalid password recovery code entered.", "user", $userId, $userId, $selectedRole);
            $error = "Invalid or expired recovery code.";
            $step = "verify";
        } else {
            $_SESSION["password_reset_verified_user_id"] = $userId;
            $step = "reset";
            $success = "Code verified. Create your new password below.";
        }
    }
}

if (isset($_POST["reset_password"])) {
    $userId = (int) ($_SESSION["password_reset_verified_user_id"] ?? 0);
    $newPassword = trim($_POST["new_password"] ?? "");
    $confirmPassword = trim($_POST["confirm_password"] ?? "");
    $selectedWorkflow = $_SESSION["password_reset_workflow"] ?? $selectedWorkflow;
    $selectedRole = $_SESSION["password_reset_role"] ?? $selectedRole;

    if ($userId <= 0) {
        $error = "Please verify your recovery code first.";
        $step = "identify";
    } elseif (strlen($newPassword) < 6) {
        $error = "New password must be at least 6 characters.";
        $step = "reset";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "The new passwords do not match.";
        $step = "reset";
    } else {
        $query = "SELECT id, full_name, role FROM users WHERE id = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        if (!$user) {
            clear_password_reset_session();
            $error = "Account not found. Please request a new recovery code.";
            $step = "identify";
        } else {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $update = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
            mysqli_stmt_bind_param($update, "si", $passwordHash, $userId);

            if (mysqli_stmt_execute($update)) {
                $markUsed = mysqli_prepare($conn, "UPDATE password_reset_codes SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL");
                mysqli_stmt_bind_param($markUsed, "i", $userId);
                mysqli_stmt_execute($markUsed);

                audit_log($conn, "password_reset", "Password was reset after email code verification for " . $user["full_name"], "user", $userId, $userId, $user["role"]);
                clear_password_reset_session();
                $step = "done";
                $success = "Password reset successful. You can now login with your new password.";
            } else {
                $error = "Could not reset password. Please try again.";
                $step = "reset";
            }
        }
    }
}

if (isset($_POST["start_over"])) {
    clear_password_reset_session();
    $step = "identify";
    $selectedWorkflow = $requestedWorkflow;
    $selectedRole = $selectedWorkflow === "student" ? "student" : "lecturer";
}

$maskedEmail = "";
if (!empty($_SESSION["password_reset_email"])) {
    $parts = explode("@", $_SESSION["password_reset_email"], 2);
    $maskedEmail = substr($parts[0], 0, 2) . str_repeat("*", max(2, strlen($parts[0]) - 2)) . "@" . ($parts[1] ?? "");
}

$workflowLabel = $selectedWorkflow === "student" ? "Student Login" : "Lecturer Portal";
$workflowSubtitle = $selectedWorkflow === "student"
    ? "Enter your registered email and matric no. to receive a recovery code."
    : "Enter your registered email to receive a recovery code.";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=professional-ui-6">
</head>
<body class="auth-page">

<div class="login-container">
    <div class="auth-brand-panel">
        <div class="brand-mark">SA</div>
        <p class="section-kicker">Account Recovery</p>
        <h1>Reset your SmartAttend password</h1>
        <p>Use a secure email verification code before creating a new password.</p>
        <div class="auth-feature-list">
            <span>Email Code</span>
            <span>10 min expiry</span>
        </div>
    </div>
    <div class="container">
        <div class="login-header">
            <p class="section-kicker">Forgot Password</p>
            <?php if ($step === "verify") { ?>
                <h2>Enter recovery code</h2>
                <p>We sent a 6-digit code to <?php echo e($maskedEmail); ?>.</p>
            <?php } elseif ($step === "reset") { ?>
                <h2>Create a new password</h2>
                <p>Your code is verified. Choose a new secure password.</p>
            <?php } elseif ($step === "done") { ?>
                <h2>Password updated</h2>
                <p>Your account is ready. You can now sign in.</p>
            <?php } elseif ($selectedWorkflow === "") { ?>
                <h2>Choose recovery type</h2>
                <p>Select the account area you were trying to access.</p>
            <?php } else { ?>
                <h2><?php echo e($workflowLabel); ?> recovery</h2>
                <p><?php echo e($workflowSubtitle); ?></p>
            <?php } ?>
        </div>

        <?php if ($error) { ?>
            <p class="alert alert-error"><?php echo e($error); ?></p>
        <?php } ?>

        <?php if ($success) { ?>
            <p class="alert alert-success"><?php echo e($success); ?></p>
        <?php } ?>

        <?php if ($step === "identify") { ?>
            <?php if ($selectedWorkflow === "") { ?>
                <div class="auth-choice-grid">
                    <a href="<?php echo e(with_context("auth/forgot_password.php?recover_as=student")); ?>" class="auth-choice-option">
                        <strong>Student Login</strong>
                        <span>Recover with email and matric no.</span>
                    </a>
                    <a href="<?php echo e(with_context("auth/forgot_password.php?recover_as=staff")); ?>" class="auth-choice-option">
                        <strong>Lecturer Portal</strong>
                        <span>Recover with registered email</span>
                    </a>
                </div>
            <?php } else { ?>
                <form method="POST">
                    <input type="hidden" name="recover_as" value="<?php echo e($selectedWorkflow); ?>">
                    <input type="email" name="email" placeholder="Registered email address" required>
                    <?php if ($selectedWorkflow === "student") { ?>
                        <input type="text" name="matric_no" id="reset-matric" placeholder="Student matric no. e.g. 2022/42335" pattern="\d{4}/\d{5}" maxlength="10" inputmode="numeric" data-matric-format title="Use four digits, slash, then five digits. Example: 2022/42335" required>
                    <?php } ?>
                    <button type="submit" name="request_code">Send Recovery Code</button>
                </form>
            <?php } ?>
        <?php } elseif ($step === "verify") { ?>
            <form method="POST">
                <input type="text" name="recovery_code" class="recovery-code-input" placeholder="6-digit code" pattern="\d{6}" maxlength="6" inputmode="numeric" autocomplete="one-time-code" required>
                <button type="submit" name="verify_code">Verify Code</button>
            </form>
            <form method="POST" class="inline-reset-action">
                <button type="submit" name="request_code" value="1" class="secondary-action">Send Code Again</button>
                <input type="hidden" name="recover_as" value="<?php echo e($_SESSION["password_reset_workflow"] ?? $selectedWorkflow); ?>">
                <input type="hidden" name="email" value="<?php echo e($_SESSION["password_reset_email"] ?? ""); ?>">
                <input type="hidden" name="matric_no" value="<?php echo e($_SESSION["password_reset_matric_no"] ?? ""); ?>">
            </form>
        <?php } elseif ($step === "reset") { ?>
            <form method="POST">
                <input type="password" name="new_password" placeholder="New password" required>
                <input type="password" name="confirm_password" placeholder="Confirm new password" required>
                <button type="submit" name="reset_password">Save New Password</button>
            </form>
        <?php } else { ?>
            <a href="login.php" class="button-link">Back to Login</a>
        <?php } ?>

        <?php if ($step !== "done" && $selectedWorkflow !== "") { ?>
            <form method="POST" class="inline-reset-action">
                <input type="hidden" name="recover_as" value="<?php echo e($selectedWorkflow); ?>">
                <button type="submit" name="start_over" class="link-button">Start over</button>
            </form>
            <p class="form-footer">Remembered your password? <a href="login.php">Back to login</a></p>
        <?php } elseif ($step !== "done") { ?>
            <p class="form-footer">Remembered your password? <a href="login.php">Back to login</a></p>
        <?php } ?>
    </div>
</div>

<script src="../assets/js/password-toggle.js?v=1"></script>
<script src="../assets/js/matric-format.js?v=1"></script>
</body>
</html>
