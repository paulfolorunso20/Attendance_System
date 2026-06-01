<?php
require_once __DIR__ . "/includes/bootstrap.php";

$dashboardLink = null;
if (isset($_SESSION["role"])) {
    if ($_SESSION["role"] === "student") {
        $dashboardLink = "student/dashboard.php";
    } elseif ($_SESSION["role"] === "lecturer") {
        $dashboardLink = "lecturer/dashboard.php";
    } elseif ($_SESSION["role"] === "admin") {
        $dashboardLink = "admin/dashboard.php";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartAttend - QR Attendance Verification</title>
    <link rel="stylesheet" href="assets/css/style.css?v=home-professional-1">
</head>

<body class="home-page">
    <main class="home-shell">
        <section class="home-panel">
            <div class="home-topbar">
                <div class="home-brand">
                    <span>SA</span>
                    <strong>SmartAttend</strong>
                </div>
                <?php if ($dashboardLink) { ?>
                    <a href="<?php echo e($dashboardLink); ?>" class="home-top-login">Dashboard</a>
                <?php } else { ?>
                    <a href="auth/login.php" class="home-top-login">Login</a>
                <?php } ?>
            </div>

            <div class="home-grid">
                <div>
                    <p class="home-kicker">University Attendance Verification</p>
                    <h1>Smart QR Attendance System for Universities</h1>
                    <p class="home-copy">
                        A web-based attendance verification system where lecturers create QR sessions
                        and students mark attendance using matric number login, face capture, and GPS checks.
                    </p>

                    <div class="home-actions">
                        <a href="auth/login.php?login_as=student" class="home-primary">Student Login</a>
                        <a href="auth/login.php?login_as=staff" class="home-secondary">Lecturer Portal</a>
                        <a href="auth/register.php" class="home-secondary">Create Account</a>
                    </div>

                    <div class="home-mini-stats" aria-label="System highlights">
                        <div><strong>QR</strong><span>Session code</span></div>
                        <div><strong>GPS</strong><span>Venue check</span></div>
                        <div><strong>CSV</strong><span>Export ready</span></div>
                    </div>
                </div>

                <aside class="home-preview" aria-label="System verification preview">
                    <div class="home-preview-header">
                        <span>System Preview</span>
                        <strong>Attendance Flow</strong>
                    </div>
                    <div class="home-qr-card">
                        <span></span><span></span><span></span><span></span>
                        <span></span><span></span><span></span><span></span>
                        <span></span><span></span><span></span><span></span>
                        <span></span><span></span><span></span><span></span>
                    </div>
                    <div class="home-check-list">
                        <div><strong>01</strong><span>Student scans QR code</span></div>
                        <div><strong>02</strong><span>Face and GPS are checked</span></div>
                        <div><strong>03</strong><span>Attendance record is saved</span></div>
                    </div>
                </aside>
            </div>

            <div class="home-bottom">
                <div class="home-feature-row">
                    <span>QR Sessions</span>
                    <span>GPS Verification</span>
                    <span>Face Capture</span>
                    <span>CSV Reports</span>
                </div>
                <a href="auth/register.php" class="home-link">Don't have an account? Sign up here</a>
            </div>
        </section>
    </main>
</body>

</html>
