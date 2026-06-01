<?php
require_once __DIR__ . "/../includes/bootstrap.php";
require_role("student");

$student_id = current_user_id();

if (isset($_POST["add_course"])) {
    $course_id = (int) $_POST["course_id"];
    $saved = false;

    if ($course_id > 0) {
        $query = "INSERT IGNORE INTO student_courses (student_id, course_id) VALUES (?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ii", $student_id, $course_id);
        $saved = mysqli_stmt_execute($stmt);
    }

    if ($saved) {
        audit_log($conn, "course_registered", "Student registered an existing course.", "course", $course_id);
    }
    set_flash($saved ? "success" : "error", $saved ? "Course registered successfully." : "Could not register course.");
    redirect_with_context("student/courses.php");
}

if (isset($_POST["add_manual_course"])) {
    $courseText = trim($_POST["manual_course"] ?? "");
    $saved = false;
    $message = "Could not register course.";

    if (!preg_match('/^([A-Z]{2,4})\s+([0-9]{3})\s+-\s+(.{5,150})$/', $courseText, $matches)) {
        $message = "Enter the course as CODE 000 - Course Title. Example: SEN 408 - Software Architecture and Design.";
    } else {
        $courseCode = strtoupper($matches[1] . " " . $matches[2]);
        $courseTitle = trim($matches[3]);

        $find_query = "SELECT id, course_title FROM courses WHERE course_code = ? LIMIT 1";
        $find_stmt = mysqli_prepare($conn, $find_query);
        mysqli_stmt_bind_param($find_stmt, "s", $courseCode);
        mysqli_stmt_execute($find_stmt);
        $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($find_stmt));

        if ($existing && strcasecmp(trim($existing["course_title"]), $courseTitle) !== 0) {
            $message = "That course code already exists with a different title. Please select it from the dropdown or use the correct title.";
        } else {
            if ($existing) {
                $course_id = (int) $existing["id"];
            } else {
                $lecturerId = null;
                $insert_query = "INSERT INTO courses (course_code, course_title, lecturer_id) VALUES (?, ?, ?)";
                $insert_stmt = mysqli_prepare($conn, $insert_query);
                mysqli_stmt_bind_param($insert_stmt, "ssi", $courseCode, $courseTitle, $lecturerId);

                try {
                    mysqli_stmt_execute($insert_stmt);
                    $course_id = mysqli_insert_id($conn);
                } catch (mysqli_sql_exception $exception) {
                    $course_id = 0;
                }
            }

            if ($course_id > 0) {
                $register_query = "INSERT IGNORE INTO student_courses (student_id, course_id) VALUES (?, ?)";
                $register_stmt = mysqli_prepare($conn, $register_query);
                mysqli_stmt_bind_param($register_stmt, "ii", $student_id, $course_id);
                $saved = mysqli_stmt_execute($register_stmt);
                if ($saved) {
                    audit_log($conn, $existing ? "course_registered" : "manual_course_added", ($existing ? "Student registered an existing course." : "Student added and registered a manual course: " . $courseCode), "course", $course_id);
                }
                $message = $saved ? "Course registered successfully. If it is unassigned, admin can assign it to a lecturer later." : "Could not register course.";
            }
        }
    }

    set_flash($saved ? "success" : "error", $message);
    redirect_with_context("student/courses.php");
}

if (isset($_POST["remove_course"])) {
    $course_id = (int) $_POST["course_id"];
    $query = "DELETE FROM student_courses WHERE student_id = ? AND course_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $student_id, $course_id);
    $saved = mysqli_stmt_execute($stmt);

    if ($saved) {
        audit_log($conn, "course_removed", "Student removed a course from their course list.", "course", $course_id);
    }
    set_flash($saved ? "success" : "error", $saved ? "Course removed from your list." : "Could not remove course.");
    redirect_with_context("student/courses.php");
}

$registered_query = "SELECT c.id, c.course_code, c.course_title, u.full_name AS lecturer_name
FROM student_courses sc
JOIN courses c ON sc.course_id = c.id
LEFT JOIN users u ON c.lecturer_id = u.id
WHERE sc.student_id = ?
ORDER BY c.course_code";
$registered_stmt = mysqli_prepare($conn, $registered_query);
mysqli_stmt_bind_param($registered_stmt, "i", $student_id);
mysqli_stmt_execute($registered_stmt);
$registered = mysqli_stmt_get_result($registered_stmt);
$registeredCount = mysqli_num_rows($registered);

$available_query = "SELECT c.id, c.course_code, c.course_title, u.full_name AS lecturer_name
FROM courses c
LEFT JOIN users u ON c.lecturer_id = u.id
WHERE c.id NOT IN (SELECT course_id FROM student_courses WHERE student_id = ?)
ORDER BY c.course_code";
$available_stmt = mysqli_prepare($conn, $available_query);
mysqli_stmt_bind_param($available_stmt, "i", $student_id);
mysqli_stmt_execute($available_stmt);
$available = mysqli_stmt_get_result($available_stmt);
$flash = get_flash();
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Courses</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=professional-ui-5">
</head>
<body class="student-courses-page">

<div class="student-courses-shell">
    <div class="student-courses-hero">
        <div>
            <p class="section-kicker">Course Registration</p>
            <h2>My Courses</h2>
            <p class="welcome">Register the courses you are taking so your attendance percentage is calculated correctly.</p>
        </div>
        <a href="dashboard.php" class="button-link secondary-action">Back to Dashboard</a>
    </div>

    <?php if ($flash) { ?>
        <p class="alert alert-<?php echo e($flash["type"]); ?>"><?php echo e($flash["message"]); ?></p>
    <?php } ?>

    <div class="course-registration-grid">
        <form method="POST" class="course-entry-card">
            <div class="settings-heading">
                <div>
                    <h3>Select Existing Course</h3>
                    <p>Choose a course already created by a lecturer or admin.</p>
                </div>
            </div>
            <label>Available Courses</label>
            <select name="course_id" required>
                <option value="">Select Course</option>
                <?php while ($course = mysqli_fetch_assoc($available)) { ?>
                    <option value="<?php echo e($course["id"]); ?>">
                        <?php echo e($course["course_code"] . " - " . $course["course_title"] . " (" . ($course["lecturer_name"] ?: "Unassigned") . ")"); ?>
                    </option>
                <?php } ?>
            </select>
            <button type="submit" name="add_course">Register Course</button>
        </form>

        <form method="POST" class="course-entry-card manual-course-form">
            <div class="settings-heading">
                <div>
                    <h3>Course Not Listed?</h3>
                    <p>Add it manually using the official course format.</p>
                </div>
            </div>
            <label>Course Details</label>
            <input type="text" name="manual_course" placeholder="Example: SEN 408 - Software Architecture and Design" required>
            <small>Required format: CODE 000 - Course Title</small>
            <button type="submit" name="add_manual_course">Add and Register Course</button>
        </form>
    </div>

    <section class="registered-courses-card">
        <div class="panel-heading">
            <h3>Registered Courses</h3>
            <span><?php echo number_format($registeredCount); ?> course<?php echo $registeredCount === 1 ? "" : "s"; ?></span>
        </div>

        <?php if ($registeredCount === 0) { ?>
            <p class="empty-state">No courses registered yet. Add a course above to start tracking your attendance percentage.</p>
        <?php } else { ?>
        <div class="table-wrap">
            <table border="1" cellpadding="10" cellspacing="0" width="100%">
                <tr>
                    <th>Course</th>
                    <th>Lecturer</th>
                    <th>Action</th>
                </tr>
                <?php while ($course = mysqli_fetch_assoc($registered)) { ?>
                <tr>
                    <td>
                        <strong><?php echo e($course["course_code"]); ?></strong><br>
                        <span><?php echo e($course["course_title"]); ?></span>
                    </td>
                    <td><?php echo e($course["lecturer_name"] ?: "Unassigned"); ?></td>
                    <td>
                        <form method="POST" class="inline-action-form">
                            <input type="hidden" name="course_id" value="<?php echo e($course["id"]); ?>">
                            <button type="submit" name="remove_course" class="danger-button">Remove</button>
                        </form>
                    </td>
                </tr>
                <?php } ?>
            </table>
        </div>
        <?php } ?>
    </section>
</div>

</body>
</html>
