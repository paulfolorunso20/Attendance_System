<?php
require_once __DIR__ . "/../includes/bootstrap.php";
$context = current_auth_context();
audit_log($conn, "logout", "User logged out.");

if ($context !== "" && isset($_SESSION["auth_contexts"][$context])) {
    unset($_SESSION["auth_contexts"][$context]);
    unset($_SESSION["auth_context"]);
    unset($_SESSION["user_id"], $_SESSION["full_name"], $_SESSION["title"], $_SESSION["position"], $_SESSION["role"]);
} else {
    session_destroy();
}

header("Location: login.php");
exit();
?>
