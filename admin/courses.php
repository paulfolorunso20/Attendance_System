<?php
require_once __DIR__ . "/../includes/bootstrap.php";
require_role("admin");
require_valid_csrf();

$lecturers = [];
$lecturer_result = mysqli_query($conn, "SELECT id, full_name, title, position FROM users WHERE role = 'lecturer' ORDER BY full_name");
while ($lecturer = mysqli_fetch_assoc($lecturer_result)) {
    $lecturers[] = $lecturer;
}

if (isset($_POST["update_course"])) {
    $courseId = (int) $_POST["course_id"];
    $courseCode = strtoupper(trim($_POST["course_code"] ?? ""));
    $courseTitle = trim($_POST["course_title"] ?? "");
    $lecturerId = trim($_POST["lecturer_id"] ?? "");
    $lecturerValue = $lecturerId === "" ? null : (int) $lecturerId;

    if ($courseCode === "" || $courseTitle === "") {
        set_flash("error", "Please enter course code and course title.");
    } else {
        $query = "UPDATE courses SET course_code = ?, course_title = ?, lecturer_id = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ssii", $courseCode, $courseTitle, $lecturerValue, $courseId);

        try {
            $saved = mysqli_stmt_execute($stmt);
        } catch (mysqli_sql_exception $exception) {
            $saved = false;
        }

        if ($saved) {
            audit_log($conn, "admin_course_updated", "Admin updated course " . $courseCode . " - " . $courseTitle, "course", $courseId);
        }
        set_flash($saved ? "success" : "error", $saved ? "Course updated successfully." : "Could not update course. Course code may already exist.");
    }

    redirect_with_context("admin/courses.php");
}

$query = "SELECT c.*, u.full_name, u.title, u.position FROM courses c LEFT JOIN users u ON c.lecturer_id = u.id ORDER BY c.course_code";
$courses = mysqli_query($conn, $query);
$flash = get_flash();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Courses</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=professional-ui-5">
</head>
<body>

<div class="dashboard-container wide-admin">
    <h2>Manage Courses</h2>

    <?php if ($flash) { ?>
        <p class="alert alert-<?php echo e($flash["type"]); ?>"><?php echo e($flash["message"]); ?></p>
    <?php } ?>

    <div class="table-wrap">
        <table border="1" cellpadding="10" cellspacing="0" width="100%">
            <tr>
                <th>Course Code</th>
                <th>Course Title</th>
                <th>Lecturer</th>
                <th>Action</th>
            </tr>

            <?php while ($row = mysqli_fetch_assoc($courses)) { ?>
            <tr>
                <form method="POST">
                    <?php render_context_input(); ?>
                    <?php render_csrf_input(); ?>
                    <td>
                        <input type="hidden" name="course_id" value="<?php echo e($row["id"]); ?>">
                        <input type="text" name="course_code" value="<?php echo e($row["course_code"]); ?>" required>
                    </td>
                    <td><input type="text" name="course_title" value="<?php echo e($row["course_title"]); ?>" required></td>
                    <td>
                        <select name="lecturer_id">
                            <option value="">Unassigned</option>
                            <?php foreach ($lecturers as $lecturer) { ?>
                                <option value="<?php echo e($lecturer["id"]); ?>" <?php echo ((int) $row["lecturer_id"] === (int) $lecturer["id"]) ? "selected" : ""; ?>>
                                    <?php echo e(lecturer_display_name($lecturer["title"], $lecturer["position"], $lecturer["full_name"])); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </td>
                    <td><button type="submit" name="update_course">Save</button></td>
                </form>
            </tr>
            <?php } ?>
        </table>
    </div>

    <br>
    <a href="<?php echo e(with_context("admin/dashboard.php")); ?>">Back to Admin Dashboard</a>
</div>

<?php render_context_script(); ?>
</body>
</html>
