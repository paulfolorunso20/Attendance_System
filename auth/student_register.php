<?php
require_once __DIR__ . "/../includes/bootstrap.php";
$error = null;

if (isset($_POST["register"])) {
    $fullName = trim($_POST["full_name"] ?? "");
    $matricNo = trim($_POST["matric_no"] ?? "");
    $department = trim($_POST["department"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = trim($_POST["password"] ?? "");

    if ($fullName === "" || $matricNo === "" || $department === "" || $email === "" || $password === "") {
        $error = "Please fill in all fields.";
    } elseif (!is_valid_matric_no($matricNo)) {
        $error = "Matric number must follow this format: 2022/42335.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $role = "student";
        $faceDescriptor = "";
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $query = "INSERT INTO users (full_name, matric_no, department, email, password, role, face_descriptor)
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "sssssss", $fullName, $matricNo, $department, $email, $passwordHash, $role, $faceDescriptor);

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
                "role" => "student",
            ]);
            audit_log($conn, "student_registered", "Student account created for " . $fullName, "user", $newUserId, $newUserId, "student");

            if (!empty($_SESSION["pending_attendance_token"])) {
                $pendingToken = $_SESSION["pending_attendance_token"];
                unset($_SESSION["pending_attendance_token"]);
                redirect_with_context("attendance/mark_attendance.php?token=" . urlencode($pendingToken));
            }

            redirect_with_context("student/dashboard.php");
        }

        $error = "Could not create account. The matric number or email may already be registered.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Registration</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=professional-ui-5">
</head>
<body class="auth-page">

<div class="login-container">
    <div class="auth-brand-panel">
        <div class="brand-mark">ST</div>
        <p class="section-kicker">Student Access</p>
        <h1>Create your attendance identity</h1>
        <p>Your profile details help lecturers identify who marked attendance during each class session.</p>
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
                <strong>Profile</strong>
                <span>Student verified</span>
            </div>
            <div class="auth-mini-card auth-mini-card-two">
                <strong>Record</strong>
                <span>Attendance saved</span>
            </div>
        </div>
        <div class="auth-feature-list">
            <span>Matric Number</span>
            <span>Department</span>
            <span>Attendance History</span>
        </div>
    </div>
    <div class="container">
        <div class="login-header">
            <p class="section-kicker">New Student</p>
            <h2>Student Registration</h2>
            <p>Fill in your official details to start marking attendance.</p>
        </div>

        <?php if ($error) { ?>
            <p class="alert alert-error"><?php echo e($error); ?></p>
        <?php } ?>

        <form method="POST">
            <?php render_context_input(); ?>
            <input type="text" name="full_name" placeholder="Full Name" required>
            <input type="text" name="matric_no" placeholder="Matric Number e.g. 2022/42335" pattern="\d{4}/\d{5}" maxlength="10" inputmode="numeric" data-matric-format title="Use four digits, slash, then five digits. Example: 2022/42335" required>
            <select name="department" required>
                <option value="">Select Department</option>
                <?php render_department_options(); ?>
            </select>
            <input type="email" name="email" placeholder="Email Address" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="register">Create Account</button>
        </form>

        <p class="form-footer">Already registered? <a href="login.php">Login</a></p>
    </div>
</div>

<script src="../assets/js/password-toggle.js?v=1"></script>
<script src="../assets/js/matric-format.js?v=1"></script>
</body>
</html>
