<?php
session_start();
include "config/db.php";
include "includes/functions.php";

require_role("admin");

$error = null;

if (isset($_POST["update_user"])) {
    $userId = (int) $_POST["user_id"];
    $fullName = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $role = trim($_POST["role"] ?? "");
    $matricNo = trim($_POST["matric_no"] ?? "");
    $department = trim($_POST["department"] ?? "");
    $title = trim($_POST["title"] ?? "");
    $position = trim($_POST["position"] ?? "");

    if ($fullName === "" || $email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid name and email.";
    } elseif (!in_array($role, ["student", "lecturer", "admin"], true)) {
        $error = "Invalid user role selected.";
    } else {
        $matricValue = $matricNo === "" ? null : $matricNo;
        $departmentValue = $department === "" ? null : $department;
        $titleValue = $title === "" ? null : $title;
        $positionValue = $position === "" ? null : $position;

        $query = "UPDATE users SET full_name = ?, email = ?, role = ?, matric_no = ?, department = ?, title = ?, position = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "sssssssi", $fullName, $email, $role, $matricValue, $departmentValue, $titleValue, $positionValue, $userId);

        try {
            $saved = mysqli_stmt_execute($stmt);
        } catch (mysqli_sql_exception $exception) {
            $saved = false;
        }

        if ($saved) {
            audit_log($conn, "admin_user_updated", "Admin updated user account: " . $fullName, "user", $userId);
        }
        set_flash($saved ? "success" : "error", $saved ? "User updated successfully." : "Could not update user. Email or matric number may already exist.");
        redirect_with_context("admin_users.php");
    }
}

$result = mysqli_query($conn, "SELECT id, full_name, title, position, matric_no, department, email, role, created_at, LENGTH(password) AS password_length FROM users ORDER BY role, full_name");
$flash = get_flash();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Users</title>
    <link rel="stylesheet" href="assets/css/style.css?v=ui-polish-1">
</head>
<body>

<div class="dashboard-container wide-admin">
    <h2>Manage Users</h2>

    <?php if ($flash) { ?>
        <p class="alert alert-<?php echo e($flash["type"]); ?>"><?php echo e($flash["message"]); ?></p>
    <?php } ?>
    <?php if ($error) { ?>
        <p class="alert alert-error"><?php echo e($error); ?></p>
    <?php } ?>

    <div class="table-wrap">
        <table border="1" cellpadding="10" cellspacing="0" width="100%">
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Matric No.</th>
                <th>Department</th>
                <th>Title</th>
                <th>Position</th>
                <th>Password</th>
                <th>Action</th>
            </tr>

            <?php while ($row = mysqli_fetch_assoc($result)) { ?>
            <tr>
                <form method="POST">
                    <td>
                        <input type="hidden" name="user_id" value="<?php echo e($row["id"]); ?>">
                        <input type="text" name="full_name" value="<?php echo e($row["full_name"]); ?>" required>
                    </td>
                    <td><input type="email" name="email" value="<?php echo e($row["email"]); ?>" required></td>
                    <td>
                        <select name="role">
                            <option value="student" <?php echo $row["role"] === "student" ? "selected" : ""; ?>>Student</option>
                            <option value="lecturer" <?php echo $row["role"] === "lecturer" ? "selected" : ""; ?>>Lecturer</option>
                            <option value="admin" <?php echo $row["role"] === "admin" ? "selected" : ""; ?>>Admin</option>
                        </select>
                    </td>
                    <td><input type="text" name="matric_no" value="<?php echo e($row["matric_no"]); ?>"></td>
                    <td>
                        <select name="department">
                            <option value="">None</option>
                            <?php render_department_options($row["department"]); ?>
                        </select>
                    </td>
                    <td>
                        <select name="title">
                            <option value="">None</option>
                            <option value="Mr" <?php echo $row["title"] === "Mr" ? "selected" : ""; ?>>Mr</option>
                            <option value="Mrs" <?php echo $row["title"] === "Mrs" ? "selected" : ""; ?>>Mrs</option>
                            <option value="Miss" <?php echo $row["title"] === "Miss" ? "selected" : ""; ?>>Miss</option>
                        </select>
                    </td>
                    <td>
                        <select name="position">
                            <option value="">None</option>
                            <option value="Prof" <?php echo $row["position"] === "Prof" ? "selected" : ""; ?>>Prof</option>
                            <option value="HOD" <?php echo $row["position"] === "HOD" ? "selected" : ""; ?>>HOD</option>
                            <option value="Dr" <?php echo $row["position"] === "Dr" ? "selected" : ""; ?>>Dr</option>
                            <option value="Normal" <?php echo $row["position"] === "Normal" ? "selected" : ""; ?>>Normal</option>
                        </select>
                    </td>
                    <td><?php echo ((int) $row["password_length"] >= 60) ? "Hashed" : "Needs login upgrade"; ?></td>
                    <td><button type="submit" name="update_user">Save</button></td>
                </form>
            </tr>
            <?php } ?>
        </table>
    </div>

    <br>
    <a href="admin_dashboard.php">Back to Admin Dashboard</a>
</div>

</body>
</html>
