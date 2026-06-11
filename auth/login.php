<?php
require_once __DIR__ . "/../includes/bootstrap.php";

$requestedLoginMode = $_GET["login_as"] ?? $_POST["login_as"] ?? "";
$loginMode = $requestedLoginMode === "staff" ? "staff" : ($requestedLoginMode === "student" ? "student" : "");
$showLoginChoice = $loginMode === "";
$loginSubtitle = $loginMode === "staff" ? "Enter your email and password." : "Enter your matric no. and password.";
$identifierPlaceholder = $loginMode === "staff" ? "Email Address" : "Matric No.";
$identifierType = $loginMode === "staff" ? "email" : "text";
$identifierAttributes = $loginMode === "staff" ? "" : ' maxlength="10" inputmode="numeric" data-matric-format';

if (isset($_POST['login'])) {

    $identifier = trim($_POST['identifier'] ?? "");
    $password = trim($_POST['password']);

    $query = "SELECT * FROM users
              WHERE (role = 'student' AND matric_no = ?)
                 OR (role IN ('lecturer', 'admin') AND email = ?)
              LIMIT 1";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ss", $identifier, $identifier);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) == 1) {

        $user = mysqli_fetch_assoc($result);

        $storedPassword = $user['password'];
        $passwordOk = password_verify($password, $storedPassword);
        $plainPasswordOk = false;

        if (!$passwordOk && hash_equals($storedPassword, $password)) {
            $passwordOk = true;
            $plainPasswordOk = true;
        }

        if (!$passwordOk) {
            audit_log($conn, "login_failed", "Failed login attempt for " . $identifier, "user", (int) $user["id"], (int) $user["id"], $user["role"] ?? null);
            $error = "Invalid login details or password.";
        } else {
            if ($plainPasswordOk || password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $update = "UPDATE users SET password = ? WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update);
                mysqli_stmt_bind_param($update_stmt, "si", $newHash, $user["id"]);
                mysqli_stmt_execute($update_stmt);
            }

            create_auth_context($user);
            audit_log($conn, "login_success", "User logged in successfully.", "user", (int) $user["id"], (int) $user["id"], trim($user["role"]));

            redirect_for_role($_SESSION['role']);

            $error = "Role not recognized. Current role is: " . e($user['role']);
        }

    } else {
        audit_log($conn, "login_failed", "Failed login attempt for unknown identifier: " . $identifier, "user", null, 0, null);
        $error = "Invalid login details or password.";
    }
}
?>


<!DOCTYPE html>
<html>

<head>
    <title>Login</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=professional-ui-5">
</head>

<body class="auth-page">

    <div class="login-container">
        <div class="auth-brand-panel">
            <div class="brand-mark">FC</div>
            <p class="section-kicker">Faculty Attendance</p>
            <h1>Smart QR Attendance Verification</h1>
            <p>Secure student attendance with QR sessions, live face capture, GPS checks, and lecturer records.</p>
            <div class="auth-visual" aria-hidden="true">
                <div class="auth-qr-card">
                    <div class="qr-mosaic">
                        <span></span><span></span><span></span><span></span>
                        <span></span><span></span><span></span><span></span>
                        <span></span><span></span><span></span><span></span>
                        <span></span><span></span><span></span><span></span>
                    </div>
                    <div class="scan-line"></div>
                </div>
                <div class="auth-mini-card auth-mini-card-one">
                    <strong>Live QR</strong>
                    <span>Active session</span>
                </div>
                <div class="auth-mini-card auth-mini-card-two">
                    <strong>GPS</strong>
                    <span>Venue verified</span>
                </div>
            </div>
            <div class="auth-feature-list">
                <span>QR Sessions</span>
                <span>Face Verification</span>
                <span>Location Check</span>
            </div>
        </div>
        <div class="container">

            <div class="login-header">
                <p class="section-kicker">Welcome Back</p>
                <h2><?php echo $showLoginChoice ? "Choose Login Type" : "Sign in to continue"; ?></h2>
                <p><?php echo $showLoginChoice ? "Select how you want to access the system." : e($loginSubtitle); ?></p>
            </div>


            <?php if (isset($error))
                echo "<p class='alert alert-error'>" . e($error) . "</p>"; ?>


            <?php if ($showLoginChoice) { ?>
                <div class="auth-choice-grid">
                    <a href="<?php echo e(with_context("auth/login.php?login_as=student")); ?>" class="auth-choice-option">
                        <strong>Student Login</strong>
                        <span>Use matric no. and password</span>
                    </a>
                    <a href="<?php echo e(with_context("auth/login.php?login_as=staff")); ?>" class="auth-choice-option">
                        <strong>Lecturer Portal</strong>
                        <span>Use email and password</span>
                    </a>
                </div>
            <?php } else { ?>
                <form method="POST">

                    <?php render_context_input(); ?>
                    <input type="hidden" name="login_as" value="<?php echo e($loginMode); ?>">
                
                    <input type="<?php echo e($identifierType); ?>" name="identifier" placeholder="<?php echo e($identifierPlaceholder); ?>"<?php echo $identifierAttributes; ?> required>

                    <input type="password" name="password" placeholder="Password" required>

                    <button type="submit" name="login">Login</button>

                </form>
            <?php } ?>

            <p class="auth-simple-link"><a href="forgot_password.php">Forgot your password?</a></p>
            <p class="auth-simple-link muted-text">Don't have an account? <a href="register.php">Sign up here</a></p>

        </div>
    </div>

<script src="../assets/js/password-toggle.js?v=1"></script>
<script src="../assets/js/matric-format.js?v=1"></script>
</body>

</html>
