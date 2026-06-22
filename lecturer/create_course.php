<?php
require_once __DIR__ . "/../includes/bootstrap.php";
require_role("lecturer");
require_valid_csrf();

$lecturer_id = current_user_id();
$error = null;
$success = null;

if (isset($_POST["create_course"])) {
    $courseCode = strtoupper(trim($_POST["course_code"] ?? ""));
    $courseTitle = trim($_POST["course_title"] ?? "");

    if ($courseCode === "" || $courseTitle === "") {
        $error = "Please enter the course code and course title.";
    } else {
        try {
            $checkQuery = "SELECT id FROM courses WHERE course_code = ? LIMIT 1";
            $checkStmt = mysqli_prepare($conn, $checkQuery);
            mysqli_stmt_bind_param($checkStmt, "s", $courseCode);
            mysqli_stmt_execute($checkStmt);
            $existingCourse = mysqli_stmt_get_result($checkStmt);

            if (mysqli_fetch_assoc($existingCourse)) {
                $error = "This course code already exists. Please use another course code.";
            } else {
                $query = "INSERT INTO courses (course_code, course_title, lecturer_id) VALUES (?, ?, ?)";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "ssi", $courseCode, $courseTitle, $lecturer_id);

                if (mysqli_stmt_execute($stmt)) {
                    audit_log($conn, "course_created", "Lecturer created course " . $courseCode . " - " . $courseTitle, "course", mysqli_insert_id($conn));
                    $success = "Course added successfully.";
                } else {
                    $error = "Could not add course right now. Please try again.";
                }
            }
        } catch (mysqli_sql_exception $exception) {
            $error = "Could not add course. Please check the course details and try again.";
        }
    }
}

$course_query = "SELECT course_code, course_title FROM courses WHERE lecturer_id = ? ORDER BY course_code";
$course_stmt = mysqli_prepare($conn, $course_query);
mysqli_stmt_bind_param($course_stmt, "i", $lecturer_id);
mysqli_stmt_execute($course_stmt);
$courses = mysqli_stmt_get_result($course_stmt);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Course</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=professional-ui-5">
</head>
<body>

<div class="dashboard-container">
    <h2>Add Course</h2>
    <p class="welcome">Lecturer: <?php echo e($_SESSION["full_name"]); ?></p>

    <?php if ($error) { ?>
        <p class="alert alert-error"><?php echo e($error); ?></p>
    <?php } ?>

    <?php if ($success) { ?>
        <p class="alert alert-success"><?php echo e($success); ?></p>
    <?php } ?>

    <form method="POST">
        <?php render_csrf_input(); ?>
        <label>Course Code</label>
        <input type="text" name="course_code" placeholder="Example: SEN 402" required>

        <label>Course Title</label>
        <input type="text" name="course_title" placeholder="Example: Software Engineering Economics" required>

        <button type="submit" name="create_course">Add Course</button>
    </form>

    <br>
    <h3>Your Courses</h3>
    <table border="1" cellpadding="10" cellspacing="0" width="100%">
        <tr>
            <th>Course Code</th>
            <th>Course Title</th>
        </tr>
        <?php while ($row = mysqli_fetch_assoc($courses)) { ?>
        <tr>
            <td><?php echo e($row["course_code"]); ?></td>
            <td><?php echo e($row["course_title"]); ?></td>
        </tr>
        <?php } ?>
    </table>

    <br>
    <a href="dashboard.php">Back to Dashboard</a>
</div>

</body>
</html>
