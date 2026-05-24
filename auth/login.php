<?php
session_start();
include __DIR__ . "/../config/db.php";
include __DIR__ . "/../includes/functions.php";

if (isset($_POST['login'])) {

    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $query = "SELECT * FROM users WHERE email = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $email);
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
            audit_log($conn, "login_failed", "Failed login attempt for " . $email, "user", (int) $user["id"], (int) $user["id"], $user["role"] ?? null);
            $error = "Invalid email or password.";
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
        audit_log($conn, "login_failed", "Failed login attempt for unknown email: " . $email, "user", null, 0, null);
        $error = "Invalid email or password.";
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
                <h2>Sign in to continue</h2>
                <p>Use your student, lecturer, or admin account.</p>
            </div>


            <?php if (isset($error))
                echo "<p class='alert alert-error'>" . e($error) . "</p>"; ?>


            <form method="POST">

                <?php render_context_input(); ?>
            
                <input type="email" name="email" placeholder="Enter Email" required>

                <input type="password" name="password" placeholder="Password" required>

                <button type="submit" name="login">Login</button>

            </form>

            <p class="form-footer">New student? <a href="student_register.php">Create student account</a></p>
            <p class="form-footer">New lecturer? <a href="lecturer_register.php">Create lecturer account</a></p>

        </div>
    </div>

</body>

</html>
