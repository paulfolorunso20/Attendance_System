<?php
require_once __DIR__ . "/../includes/bootstrap.php";
if (!isset($_SESSION["user_id"]) && isset($_GET["token"])) {
    $pendingToken = trim((string) $_GET["token"]);
    $_SESSION["pending_attendance_token"] = $pendingToken;
    redirect_with_context("auth/login.php?login_as=student&attendance_token=" . urlencode($pendingToken));
    exit();
}

require_role("student");

if (!isset($_GET['token'])) {
    die("Invalid attendance link.");
}

$token = $_GET['token'];
$student_id = current_user_id();
$success = null;
$error = null;
$profileError = null;

$student_query = "SELECT full_name, matric_no, department, email, face_descriptor FROM users WHERE id = ? LIMIT 1";
$student_stmt = mysqli_prepare($conn, $student_query);
mysqli_stmt_bind_param($student_stmt, "i", $student_id);
mysqli_stmt_execute($student_stmt);
$student_result = mysqli_stmt_get_result($student_stmt);
$student = mysqli_fetch_assoc($student_result);

if (isset($_POST["save_profile"])) {
    $fullName = trim($_POST["full_name"] ?? "");
    $matricNo = trim($_POST["matric_no"] ?? "");
    $department = trim($_POST["department"] ?? "");
    $email = trim($_POST["email"] ?? "");

    if ($fullName === "" || $matricNo === "" || $department === "" || $email === "") {
        $profileError = "Please enter your full name, matric number, department, and email address.";
    } elseif (!is_valid_matric_no($matricNo)) {
        $profileError = "Matric number must follow this format: 2022/42335.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $profileError = "Please enter a valid email address.";
    } else {
        $update = "UPDATE users SET full_name = ?, matric_no = ?, department = ?, email = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update);
        mysqli_stmt_bind_param($update_stmt, "ssssi", $fullName, $matricNo, $department, $email, $student_id);

        if (mysqli_stmt_execute($update_stmt)) {
            $_SESSION["full_name"] = $fullName;
            $student["full_name"] = $fullName;
            $student["matric_no"] = $matricNo;
            $student["department"] = $department;
            $student["email"] = $email;
            audit_log($conn, "attendance_profile_updated", "Student updated details during attendance marking.", "user", $student_id);
        } else {
            $profileError = "Could not save your details. The matric number or email may already be used.";
        }
    }
}

$profileComplete = !empty($student["full_name"]) &&
    strtolower(trim($student["full_name"])) !== "student" &&
    !empty($student["matric_no"]) &&
    !empty($student["department"]) &&
    !empty($student["email"]);

$faceEnrolled = is_valid_face_descriptor($student["face_descriptor"] ?? "");

$session_query = "SELECT s.*, c.course_code, c.course_title
                  FROM attendance_sessions s
                  JOIN courses c ON s.course_id = c.id
                  WHERE s.session_token = ?
                  LIMIT 1";
$stmt = mysqli_prepare($conn, $session_query);
mysqli_stmt_bind_param($stmt, "s", $token);
mysqli_stmt_execute($stmt);
$session_result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($session_result) == 0) {
    die("Attendance session not found.");
}

$session = mysqli_fetch_assoc($session_result);

if (strtotime($session['expires_at']) < time()) {
    die("This attendance session has expired.");
}

if (!empty($session["closed_at"])) {
    die("This attendance session has been closed by the lecturer.");
}

$session_id = (int) $session['id'];

$check_query = "SELECT id FROM attendance_records WHERE session_id = ? AND student_id = ? LIMIT 1";
$check_stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($check_stmt, "ii", $session_id, $student_id);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($check_result) > 0) {
    die("You have already marked attendance for this session.");
}

if (isset($_POST["save_face_profile"]) && $profileComplete) {
    $enrolledDescriptor = trim($_POST["enrolled_face_descriptor"] ?? "");
    $enrolledSnapshot = $_POST["enrolled_face_snapshot"] ?? "";

    if (!is_valid_face_descriptor($enrolledDescriptor)) {
        $error = "Face enrollment failed. Please capture a clear face image.";
    } else {
        save_face_snapshot($student_id, 0, $enrolledSnapshot);
        $update = "UPDATE users SET face_descriptor = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update);
        mysqli_stmt_bind_param($update_stmt, "si", $enrolledDescriptor, $student_id);

        if (mysqli_stmt_execute($update_stmt)) {
            $student["face_descriptor"] = $enrolledDescriptor;
            $faceEnrolled = true;
            audit_log($conn, "face_profile_saved", "Student saved face profile.", "user", $student_id);
        } else {
            $error = "Could not save face profile.";
        }
    }
}

if (isset($_POST['mark']) && $profileComplete && $faceEnrolled) {
    $studentLat = filter_input(INPUT_POST, "latitude", FILTER_VALIDATE_FLOAT);
    $studentLng = filter_input(INPUT_POST, "longitude", FILTER_VALIDATE_FLOAT);
    $studentAccuracy = filter_input(INPUT_POST, "location_accuracy", FILTER_VALIDATE_FLOAT);
    $faceConfirmed = ($_POST["face_confirmed"] ?? "0") === "1";
    $faceSnapshot = $_POST["face_snapshot"] ?? "";
    $capturedDescriptor = trim($_POST["captured_face_descriptor"] ?? "");

    if ($studentLat === false || $studentLng === false || $studentLat === null || $studentLng === null) {
        $error = "Location verification failed. Please allow GPS access and try again.";
    } else {
        $radius = (float) $session["radius_meters"];
        $accuracy = ($studentAccuracy !== false && $studentAccuracy !== null) ? max(0, (float) $studentAccuracy) : null;
        $maxAllowedAccuracy = $radius >= 500 ? 2500 : max(100, $radius * 2);
        $distance = distance_in_meters($session["latitude"], $session["longitude"], $studentLat, $studentLng);
        $accuracyBuffer = $accuracy !== null ? min($accuracy, $maxAllowedAccuracy) : 0;
        $demoBuffer = $radius >= 500 ? 250 : 0;
        $effectiveRadius = $radius + $accuracyBuffer + $demoBuffer;
        $locationVerified = $accuracy === null || $accuracy <= $maxAllowedAccuracy;
        $locationVerified = $locationVerified && $distance <= $effectiveRadius;
        $snapshotPath = save_face_snapshot($student_id, $session_id, $faceSnapshot);
        $faceVerified = $faceConfirmed &&
            $snapshotPath !== null &&
            face_descriptor_matches($student["face_descriptor"], $capturedDescriptor);

        if (!$faceVerified) {
            audit_log($conn, "attendance_failed_face", "Attendance attempt failed face verification.", "attendance_session", $session_id);
            $error = "Face verification failed. The captured face does not match the registered student face.";
        } elseif (!$locationVerified) {
            audit_log($conn, "attendance_failed_location", "Attendance attempt failed location verification.", "attendance_session", $session_id);
            if ($accuracy !== null && $accuracy > $maxAllowedAccuracy) {
                $error = "Location verification failed. Your phone GPS accuracy is too weak right now (about " . round($accuracy) . "m). Move near a window/open area, turn on Precise Location, and tap Retry GPS.";
            } else {
                $error = "Location verification failed. Your phone location is about " . round($distance) . " meters from the lecture venue. Retry GPS or ask the lecturer to recreate the session at the venue.";
            }
        } else {
            $insert = "INSERT INTO attendance_records
                (session_id, student_id, status, face_verified, location_verified, latitude, longitude, distance_meters, face_snapshot)
                VALUES (?, ?, 'present', 1, 1, ?, ?, ?, ?)";
            $insert_stmt = mysqli_prepare($conn, $insert);
            mysqli_stmt_bind_param($insert_stmt, "iiddds", $session_id, $student_id, $studentLat, $studentLng, $distance, $snapshotPath);

            if (mysqli_stmt_execute($insert_stmt)) {
                audit_log($conn, "attendance_marked", "Student marked verified attendance.", "attendance_session", $session_id);
                $success = "Attendance marked successfully. QR, face, and location checks passed.";
            } else {
                $error = "Could not mark attendance. You may have already submitted this session.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Mark Attendance</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=attendance-verify-1">
</head>
<body class="attendance-page">

<div class="container wide-container attendance-shell">
    <div class="attendance-hero">
        <div>
            <span class="attendance-kicker">Verified attendance</span>
            <h2>Mark Attendance</h2>
            <p><?php echo e($session["course_code"] . " - " . $session["course_title"]); ?></p>
        </div>
        <div class="student-identity">
            <span>Signed in as</span>
            <strong><?php echo e($student["full_name"] ?? $_SESSION["full_name"]); ?></strong>
        </div>
    </div>

    <?php if ($success) { ?>
        <p class="alert alert-success"><?php echo e($success); ?></p>
        <p><a href="<?php echo e(with_context("student/history.php")); ?>">View Attendance History</a></p>
    <?php } ?>

    <?php if ($error) { ?>
        <p class="alert alert-error"><?php echo e($error); ?></p>
    <?php } ?>

    <?php if ($profileError) { ?>
        <p class="alert alert-error"><?php echo e($profileError); ?></p>
    <?php } ?>

    <?php if (!$success && !$profileComplete) { ?>
    <form method="POST" class="profile-form">
        <h3>Confirm Student Details</h3>
        <label>Full Name</label>
        <input type="text" name="full_name" value="<?php echo e($student["full_name"] ?? ""); ?>" required>

        <label>Matric Number</label>
        <input type="text" name="matric_no" value="<?php echo e($student["matric_no"] ?? ""); ?>" pattern="\d{4}/\d{5}" maxlength="10" inputmode="numeric" data-matric-format title="Use four digits, slash, then five digits. Example: 2022/42335" required>

        <label>Department</label>
        <select name="department" required>
            <option value="">Select Department</option>
            <?php render_department_options($student["department"] ?? ""); ?>
        </select>

        <label>Email Address</label>
        <input type="email" name="email" value="<?php echo e($student["email"] ?? ""); ?>" required>

        <button type="submit" name="save_profile">Save and Continue</button>
    </form>
    <?php } ?>

    <?php if (!$success && $profileComplete && !$faceEnrolled) { ?>
    <div class="attendance-section-intro">
        <span class="verification-chip">One-time setup</span>
        <p>Register your face once before marking attendance.</p>
    </div>
    <form method="POST" id="faceEnrollForm">
        <div class="verification-card">
        <div class="verification-grid">
            <div class="face-scan-panel">
                <div class="face-camera-frame">
                    <video id="enrollCamera" autoplay playsinline></video>
                    <canvas id="enrollCanvas" width="480" height="360"></canvas>
                    <div class="face-scan-guide">
                        <span></span>
                    </div>
                    <div class="face-scan-line"></div>
                    <div class="camera-mode-badge" id="enrollCameraMode">Starting camera</div>
                </div>
                <input type="file" id="enrollPhotoInput" accept="image/*" capture="user" class="camera-file-input">
                <div class="face-action-row">
                    <button type="button" class="secondary-button face-capture-button primary-scan-action" id="captureEnrollFace">Run Live Face Scan</button>
                    <button type="button" class="secondary-button face-capture-button fallback-scan-action" id="openEnrollCamera">Photo Fallback</button>
                </div>
                <p id="enrollFaceStatus" class="status-text">Keep your face inside the frame and use Live Face Scan first.</p>
            </div>

            <div class="verification-panel">
                <span class="verification-panel-label">Face profile</span>
                <h3><?php echo e($student["full_name"]); ?></h3>
                <p>We will store a secure face profile from this scan and compare it against future attendance submissions.</p>
                <div class="verification-steps">
                    <span>1. Center face</span>
                    <span>2. Run scan</span>
                    <span>3. Save profile</span>
                </div>
            </div>
        </div>

        <input type="hidden" name="enrolled_face_snapshot" id="enrolledFaceSnapshot">
        <input type="hidden" name="enrolled_face_descriptor" id="enrolledFaceDescriptor">
        <button type="submit" name="save_face_profile" id="saveFaceProfile" disabled>Save Face Profile</button>
        </div>
    </form>
    <?php } ?>

    <?php if (!$success && $profileComplete && $faceEnrolled) { ?>
    <div class="attendance-section-intro">
        <span class="verification-chip">Live verification</span>
        <p>Complete the face scan and GPS check before submitting.</p>
    </div>
    <form method="POST" id="attendanceForm">
        <div class="verification-card">
        <div class="verification-grid">
            <div class="face-scan-panel">
                <div class="face-camera-frame">
                    <video id="camera" autoplay playsinline></video>
                    <canvas id="snapshotCanvas" width="480" height="360"></canvas>
                    <div class="face-scan-guide">
                        <span></span>
                    </div>
                    <div class="face-scan-line"></div>
                    <div class="camera-mode-badge" id="cameraMode">Starting camera</div>
                </div>
                <input type="file" id="attendancePhotoInput" accept="image/*" capture="user" class="camera-file-input">
                <div class="face-action-row">
                    <button type="button" class="secondary-button face-capture-button primary-scan-action" id="captureFace">Run Live Face Scan</button>
                    <button type="button" class="secondary-button face-capture-button fallback-scan-action" id="openAttendanceCamera">Photo Fallback</button>
                </div>
                <p id="faceStatus" class="status-text">Keep your face inside the frame and use Live Face Scan first.</p>
            </div>

            <div class="verification-panel">
                <span class="verification-panel-label">Session check</span>
                <h3><?php echo e($session["course_code"]); ?></h3>
                <div class="session-check-list">
                    <p><strong>Expires</strong><span><?php echo e($session["expires_at"]); ?></span></p>
                    <p><strong>Allowed radius</strong><span><?php echo e($session["radius_meters"]); ?> meters</span></p>
                </div>
                <p id="locationStatus" class="status-text">Waiting for GPS location.</p>
                <button type="button" class="secondary-button compact-button" id="retryLocation">Retry GPS</button>
            </div>
        </div>

        <input type="hidden" name="latitude" id="latitude">
        <input type="hidden" name="longitude" id="longitude">
        <input type="hidden" name="location_accuracy" id="locationAccuracy">
        <input type="hidden" name="face_snapshot" id="faceSnapshot">
        <input type="hidden" name="face_confirmed" id="faceConfirmed" value="0">
        <input type="hidden" name="captured_face_descriptor" id="capturedFaceDescriptor">

        <button type="submit" name="mark" id="submitAttendance" disabled>Submit Verified Attendance</button>
        </div>
    </form>
    <?php } ?>

    <br>
    <a href="<?php echo e(with_context("student/dashboard.php")); ?>">Back to Dashboard</a>
</div>

<script>
const video = document.getElementById("camera");
const canvas = document.getElementById("snapshotCanvas");
const faceStatus = document.getElementById("faceStatus");
const locationStatus = document.getElementById("locationStatus");
const submitButton = document.getElementById("submitAttendance");
const faceConfirmed = document.getElementById("faceConfirmed");
const faceSnapshot = document.getElementById("faceSnapshot");
const capturedFaceDescriptor = document.getElementById("capturedFaceDescriptor");
const latitude = document.getElementById("latitude");
const longitude = document.getElementById("longitude");
const locationAccuracy = document.getElementById("locationAccuracy");
const retryLocation = document.getElementById("retryLocation");
const enrollVideo = document.getElementById("enrollCamera");
const enrollCanvas = document.getElementById("enrollCanvas");
const enrollFaceStatus = document.getElementById("enrollFaceStatus");
const saveFaceProfile = document.getElementById("saveFaceProfile");
const enrolledFaceSnapshot = document.getElementById("enrolledFaceSnapshot");
const enrolledFaceDescriptor = document.getElementById("enrolledFaceDescriptor");
const attendancePhotoInput = document.getElementById("attendancePhotoInput");
const enrollPhotoInput = document.getElementById("enrollPhotoInput");
const cameraMode = document.getElementById("cameraMode");
const enrollCameraMode = document.getElementById("enrollCameraMode");
const openAttendanceCamera = document.getElementById("openAttendanceCamera");
const openEnrollCamera = document.getElementById("openEnrollCamera");

let hasLocation = false;
let hasFace = false;
let cameraStreamReady = false;
let enrollCameraStreamReady = false;
const sessionRadiusMeters = <?php echo json_encode((float) $session["radius_meters"]); ?>;
const demoAccuracyLimit = sessionRadiusMeters >= 500 ? 2500 : Math.max(100, sessionRadiusMeters * 2);

function updateSubmitState() {
    if (submitButton) {
        submitButton.disabled = !(hasLocation && hasFace);
    }
}

async function startCamera(targetVideo, statusElement, modeElement) {
    try {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            statusElement.textContent = "Live camera is blocked here. Tap Scan Face to open your phone camera.";
            if (modeElement) {
                modeElement.textContent = "Camera fallback";
                modeElement.classList.add("camera-mode-warning");
            }
            return false;
        }

        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" }, audio: false });
        targetVideo.srcObject = stream;
        statusElement.textContent = "Live camera ready. Keep your face centered and tap Scan Face.";
        if (modeElement) {
            modeElement.textContent = "Live camera";
            modeElement.classList.remove("camera-mode-warning");
        }
        return true;
    } catch (error) {
        statusElement.textContent = "Live camera is blocked on this local link. Tap Scan Face to open your phone camera.";
        if (modeElement) {
            modeElement.textContent = "Camera fallback";
            modeElement.classList.add("camera-mode-warning");
        }
        return false;
    }
}

function getLocation() {
    if (!locationStatus) {
        return;
    }

    if (!navigator.geolocation) {
        locationStatus.textContent = "GPS is not available in this browser. Try Chrome and allow location permission.";
        return;
    }

    locationStatus.textContent = "Capturing best GPS reading. Please wait...";
    hasLocation = false;
    updateSubmitState();

    captureBestPosition(function (position) {
        const accuracy = position.coords.accuracy || 0;
        latitude.value = position.coords.latitude;
        longitude.value = position.coords.longitude;
        if (locationAccuracy) {
            locationAccuracy.value = accuracy;
        }
        hasLocation = true;
        if (accuracy > demoAccuracyLimit) {
            locationStatus.textContent = "GPS captured, but accuracy is too weak right now: about " + Math.round(accuracy) + " meters. Move near a window/open area and retry.";
            hasLocation = false;
        } else if (accuracy > sessionRadiusMeters) {
            locationStatus.textContent = "GPS captured with weak phone accuracy: about " + Math.round(accuracy) + " meters. Demo tolerance is active for this session.";
        } else {
            locationStatus.textContent = "GPS location captured. Accuracy: about " + Math.round(accuracy) + " meters.";
        }
        updateSubmitState();
    }, function (error) {
        hasLocation = false;
        updateSubmitState();

        if (error.code === error.PERMISSION_DENIED) {
            locationStatus.textContent = "Location permission is blocked. Tap the site icon in Chrome, open Permissions, and allow Location.";
            return;
        }

        if (error.code === error.POSITION_UNAVAILABLE) {
            locationStatus.textContent = "GPS location is unavailable. Move near a window, keep phone location on, then tap Retry GPS.";
            return;
        }

        if (error.code === error.TIMEOUT) {
            locationStatus.textContent = "GPS timed out. Keep location on and tap Retry GPS.";
            return;
        }

        locationStatus.textContent = "GPS could not be captured. Please tap Retry GPS.";
    });
}

function captureBestPosition(onSuccess, onError) {
    let bestPosition = null;
    let lastError = null;
    let finished = false;
    let watchId = null;
    const options = { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 };

    const finish = function () {
        if (finished) {
            return;
        }
        finished = true;
        if (watchId !== null) {
            navigator.geolocation.clearWatch(watchId);
        }
        if (bestPosition) {
            onSuccess(bestPosition);
        } else {
            onError(lastError || { code: 0 });
        }
    };

    const acceptPosition = function (position) {
        if (!bestPosition || (position.coords.accuracy || 99999) < (bestPosition.coords.accuracy || 99999)) {
            bestPosition = position;
        }
        if ((position.coords.accuracy || 99999) <= 50) {
            finish();
        }
    };

    try {
        watchId = navigator.geolocation.watchPosition(acceptPosition, function (error) {
            lastError = error;
        }, options);

        navigator.geolocation.getCurrentPosition(acceptPosition, function (error) {
            lastError = error;
        }, options);

        window.setTimeout(finish, 15000);
    } catch (error) {
        navigator.geolocation.getCurrentPosition(onSuccess, onError, options);
    }
}

function buildFaceDescriptor(sourceCanvas) {
    const smallCanvas = document.createElement("canvas");
    smallCanvas.width = 16;
    smallCanvas.height = 16;
    const smallContext = smallCanvas.getContext("2d");
    smallContext.drawImage(sourceCanvas, 0, 0, 16, 16);
    const pixels = smallContext.getImageData(0, 0, 16, 16).data;
    const values = [];
    let total = 0;

    for (let i = 0; i < pixels.length; i += 4) {
        const gray = Math.round((pixels[i] + pixels[i + 1] + pixels[i + 2]) / 3);
        values.push(gray);
        total += gray;
    }

    const average = total / values.length;
    let bits = "";
    values.forEach(value => {
        bits += value >= average ? "1" : "0";
    });

    let hex = "";
    for (let i = 0; i < bits.length; i += 4) {
        hex += parseInt(bits.substring(i, i + 4), 2).toString(16);
    }

    return hex;
}

async function faceVisible(sourceCanvas) {
    if ("FaceDetector" in window) {
        try {
            const detector = new FaceDetector({ fastMode: true, maxDetectedFaces: 1 });
            const faces = await detector.detect(sourceCanvas);
            return faces.length > 0;
        } catch (error) {
            return true;
        }
    }

    return true;
}

function drawImageFileToCanvas(file, targetCanvas, callback) {
    if (!file) {
        return;
    }

    const reader = new FileReader();
    reader.onload = function (event) {
        const image = new Image();
        image.onload = function () {
            const context = targetCanvas.getContext("2d");
            context.clearRect(0, 0, targetCanvas.width, targetCanvas.height);
            context.drawImage(image, 0, 0, targetCanvas.width, targetCanvas.height);
            targetCanvas.style.display = "block";
            callback();
        }; 
        image.src = event.target.result;
    };
    reader.readAsDataURL(file);
}

function captureVideoFrame(targetVideo, targetCanvas) {
    if (!targetVideo || targetVideo.readyState < 2 || targetVideo.videoWidth === 0 || targetVideo.videoHeight === 0) {
        return false;
    }

    const context = targetCanvas.getContext("2d");
    context.clearRect(0, 0, targetCanvas.width, targetCanvas.height);
    context.drawImage(targetVideo, 0, 0, targetCanvas.width, targetCanvas.height);
    targetCanvas.style.display = "block";
    return true;
}

async function processAttendanceFace() {
    faceStatus.textContent = "Scanning face. Hold still...";
    faceSnapshot.value = canvas.toDataURL("image/jpeg", 0.85);
    capturedFaceDescriptor.value = buildFaceDescriptor(canvas);
    hasFace = await faceVisible(canvas);

    faceConfirmed.value = hasFace ? "1" : "0";
    faceStatus.textContent = hasFace ? "Face scan captured. Complete GPS check to submit." : "No face detected. Please scan again in better lighting.";
    updateSubmitState();
}

async function processEnrollFace() {
    enrollFaceStatus.textContent = "Scanning face profile. Hold still...";
    const hasEnrollFace = await faceVisible(enrollCanvas);

    if (!hasEnrollFace) {
        enrollFaceStatus.textContent = "No face detected. Please scan again in better lighting.";
        saveFaceProfile.disabled = true;
        return;
    }

    enrolledFaceSnapshot.value = enrollCanvas.toDataURL("image/jpeg", 0.85);
    enrolledFaceDescriptor.value = buildFaceDescriptor(enrollCanvas);
    enrollFaceStatus.textContent = "Face profile scan captured. Save to continue.";
    saveFaceProfile.disabled = false;
}

const captureFaceButton = document.getElementById("captureFace");
if (captureFaceButton) {
    captureFaceButton.addEventListener("click", async function () {
        if (!cameraStreamReady) {
            faceStatus.textContent = "Live camera is unavailable, opening photo fallback.";
            attendancePhotoInput.click();
            return;
        }

        captureFaceButton.disabled = true;
        canvas.closest(".face-camera-frame").classList.add("is-scanning");
        faceStatus.textContent = "Analyzing face position...";
        await new Promise(resolve => window.setTimeout(resolve, 850));
        if (!captureVideoFrame(video, canvas)) {
            faceStatus.textContent = "Camera preview is not ready. Use Photo Fallback or try again.";
            captureFaceButton.disabled = false;
            canvas.closest(".face-camera-frame").classList.remove("is-scanning");
            return;
        }
        await processAttendanceFace();
        canvas.closest(".face-camera-frame").classList.remove("is-scanning");
        captureFaceButton.disabled = false;
    });
}

if (openAttendanceCamera) {
    openAttendanceCamera.addEventListener("click", function () {
        attendancePhotoInput.click();
    });
}

if (attendancePhotoInput) {
    attendancePhotoInput.addEventListener("change", function () {
        drawImageFileToCanvas(this.files[0], canvas, processAttendanceFace);
    });
}

const captureEnrollButton = document.getElementById("captureEnrollFace");
if (captureEnrollButton) {
    captureEnrollButton.addEventListener("click", async function () {
        if (!enrollCameraStreamReady) {
            enrollFaceStatus.textContent = "Live camera is unavailable, opening photo fallback.";
            enrollPhotoInput.click();
            return;
        }

        captureEnrollButton.disabled = true;
        enrollCanvas.closest(".face-camera-frame").classList.add("is-scanning");
        enrollFaceStatus.textContent = "Analyzing face position...";
        await new Promise(resolve => window.setTimeout(resolve, 850));
        if (!captureVideoFrame(enrollVideo, enrollCanvas)) {
            enrollFaceStatus.textContent = "Camera preview is not ready. Use Photo Fallback or try again.";
            captureEnrollButton.disabled = false;
            enrollCanvas.closest(".face-camera-frame").classList.remove("is-scanning");
            return;
        }
        await processEnrollFace();
        enrollCanvas.closest(".face-camera-frame").classList.remove("is-scanning");
        captureEnrollButton.disabled = false;
    });
}

if (openEnrollCamera) {
    openEnrollCamera.addEventListener("click", function () {
        enrollPhotoInput.click();
    });
}

if (enrollPhotoInput) {
    enrollPhotoInput.addEventListener("change", function () {
        drawImageFileToCanvas(this.files[0], enrollCanvas, processEnrollFace);
    });
}

if (video) {
    startCamera(video, faceStatus, cameraMode).then(function (ready) {
        cameraStreamReady = ready;
    });
}

if (enrollVideo) {
    startCamera(enrollVideo, enrollFaceStatus, enrollCameraMode).then(function (ready) {
        enrollCameraStreamReady = ready;
    });
}

if (retryLocation) {
    retryLocation.addEventListener("click", getLocation);
}

getLocation();
</script>
<script src="../assets/js/matric-format.js?v=1"></script>

</body>
</html>
