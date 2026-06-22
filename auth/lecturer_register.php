<?php
require_once __DIR__ . "/../includes/bootstrap.php";
require_valid_csrf();
$error = null;

if (isset($_POST["register"])) {
    $title = trim($_POST["title"] ?? "");
    $position = trim($_POST["position"] ?? "");
    $department = trim($_POST["department"] ?? "");
    $fullName = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $inviteCode = trim($_POST["invite_code"] ?? "");
    $password = trim($_POST["password"] ?? "");

    if ($title === "" || $position === "" || $department === "" || $fullName === "" || $email === "" || $inviteCode === "" || $password === "") {
        $error = "Please fill in all fields.";
    } elseif (!hash_equals($lecturerInviteCode, $inviteCode)) {
        audit_log($conn, "lecturer_invite_failed", "Invalid lecturer invite code used for " . $email, "user", null, 0, "lecturer");
        $error = "Invalid lecturer invite code. Please contact the administrator.";
    } elseif (!in_array($title, ["Mr", "Mrs", "Miss"], true)) {
        $error = "Please select a valid title.";
    } elseif (!in_array($position, ["Prof", "HOD", "Dr", "Normal"], true)) {
        $error = "Please select a valid position.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $role = "lecturer";
        $matricNo = null;
        $faceDescriptor = "";
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $query = "INSERT INTO users (full_name, title, position, department, matric_no, email, password, role, face_descriptor)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "sssssssss", $fullName, $title, $position, $department, $matricNo, $email, $passwordHash, $role, $faceDescriptor);

        try {
            $created = mysqli_stmt_execute($stmt);
        } catch (mysqli_sql_exception $exception) {
            $created = false;
        }

        if ($created) {
            $newUserId = mysqli_insert_id($conn);
            create_auth_context([
                "id" => $newUserId,
                "full_name" => $fullName,
                "title" => $title,
                "position" => $position,
                "role" => "lecturer",
            ]);
            audit_log($conn, "lecturer_registered", "Lecturer account created with invite code for " . $fullName, "user", $newUserId, $newUserId, "lecturer");
            redirect_with_context("lecturer/dashboard.php");
        }

        $error = "Could not create account. The email may already be registered.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Lecturer Registration</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=professional-ui-5">
</head>
<body class="auth-page">

<div class="login-container">
    <div class="auth-brand-panel">
        <div class="brand-mark"><img src="../assets/img/smartattend-logo.svg" alt="" aria-hidden="true"></div>
        <p class="section-kicker">Lecturer Access</p>
        <h1>Manage class sessions professionally</h1>
        <p>Create secure QR sessions, monitor live attendance, and export verified class records.</p>
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
                <strong>Session</strong>
                <span>QR active</span>
            </div>
            <div class="auth-mini-card auth-mini-card-two">
                <strong>Report</strong>
                <span>Records ready</span>
            </div>
        </div>
        <div class="auth-feature-list">
            <span>Course Sessions</span>
            <span>Live QR</span>
            <span>Reports</span>
        </div>
    </div>
    <div class="container">
        <div class="login-header">
            <p class="section-kicker">New Lecturer</p>
            <h2>Lecturer Registration</h2>
            <p>Set up your lecturer profile and department.</p>
        </div>

        <?php if ($error) { ?>
            <p class="alert alert-error"><?php echo e($error); ?></p>
        <?php } ?>

        <form method="POST">
            <?php render_context_input(); ?>
            <?php render_csrf_input(); ?>
            <select name="title" required>
                <option value="">Select Title</option>
                <option value="Mr">Mr</option>
                <option value="Mrs">Mrs</option>
                <option value="Miss">Miss</option>
            </select>
            <select name="position" required>
                <option value="">Select Position</option>
                <option value="Prof">Prof</option>
                <option value="HOD">HOD</option>
                <option value="Dr">Dr</option>
                <option value="Normal">Normal Lecturer</option>
            </select>
            <select name="department" required>
                <option value="">Select Department</option>
                <?php render_department_options(); ?>
            </select>
            <input type="text" name="full_name" placeholder="Full Name" required>
            <input type="email" name="email" placeholder="Email Address" required>
            <input type="text" name="invite_code" placeholder="Lecturer Invite Code" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="register">Create Lecturer Account</button>
        </form>

        <p class="form-footer">Already registered? <a href="login.php">Login</a></p>
    </div>
</div>

<script src="../assets/js/password-toggle.js?v=1"></script>
</body>
</html>

