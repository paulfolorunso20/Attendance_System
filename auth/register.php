<?php
require_once __DIR__ . "/../includes/bootstrap.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create Account</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=professional-ui-5">
</head>
<body class="auth-page">

<div class="auth-choice-shell">
    <div class="auth-choice-card">
        <div class="brand-mark"><img src="../assets/img/smartattend-logo.svg" alt="" aria-hidden="true"></div>
        <p class="section-kicker">Create Account</p>
        <h1>Choose your account type</h1>
        <p class="auth-choice-copy">Select the role that matches how you will use SmartAttend.</p>

        <div class="auth-choice-grid">
            <a href="student_register.php" class="auth-choice-option">
                <strong>Student</strong>
                <span>Register with your matric number, department, email, and password.</span>
            </a>
            <a href="lecturer_register.php" class="auth-choice-option">
                <strong>Lecturer</strong>
                <span>Create a lecturer account to manage courses and QR attendance sessions.</span>
            </a>
        </div>

        <p class="auth-simple-link muted-text">Already have an account? <a href="login.php">Login here</a></p>
    </div>
</div>

</body>
</html>
