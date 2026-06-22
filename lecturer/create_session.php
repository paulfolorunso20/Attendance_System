<?php
require_once __DIR__ . "/../includes/bootstrap.php";
require_role("lecturer");
require_valid_csrf();

$lecturer_id = current_user_id();
$courses = [];
$createdSession = null;
$qr_link = null;
$flash = get_flash();

$course_query = "SELECT * FROM courses WHERE lecturer_id = ? ORDER BY course_code";
$course_stmt = mysqli_prepare($conn, $course_query);
mysqli_stmt_bind_param($course_stmt, "i", $lecturer_id);
mysqli_stmt_execute($course_stmt);
$course_result = mysqli_stmt_get_result($course_stmt);

while ($row = mysqli_fetch_assoc($course_result)) {
    $courses[] = $row;
}

if (isset($_POST["end_created_session"])) {
    $sessionId = (int) ($_POST["session_id"] ?? 0);
    $query = "UPDATE attendance_sessions SET closed_at = NOW() WHERE id = ? AND lecturer_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $sessionId, $lecturer_id);
    $saved = mysqli_stmt_execute($stmt);

    if ($saved) {
        audit_log($conn, "session_closed", "Lecturer ended a QR session from create session page.", "attendance_session", $sessionId);
    }

    set_flash($saved ? "success" : "error", $saved ? "Session ended successfully." : "Could not end session.");
    redirect_with_context("lecturer/create_session.php");
}

if (isset($_POST['create'])) {
    $course_id = (int) $_POST['course_id'];
    $duration = max(1, min(180, (int) ($_POST['duration_minutes'] ?? 10)));
    $latitude = (float) $_POST['latitude'];
    $longitude = (float) $_POST['longitude'];
    $locationConfirmed = ($_POST["location_confirmed"] ?? "0") === "1";
    $venueAccuracy = filter_input(INPUT_POST, "venue_accuracy", FILTER_VALIDATE_FLOAT);
    $radius = max(10, min(1000, (int) ($_POST['radius_meters'] ?? 100)));
    $token = random_token(24);
    $expires_at = date("Y-m-d H:i:s", strtotime("+{$duration} minutes"));

    $course_check = "SELECT id, course_code, course_title FROM courses WHERE id = ? AND lecturer_id = ? LIMIT 1";
    $check_stmt = mysqli_prepare($conn, $course_check);
    mysqli_stmt_bind_param($check_stmt, "ii", $course_id, $lecturer_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);

    $manualLocation = ($_POST["manual_location"] ?? "0") === "1";

    if (!$locationConfirmed || $venueAccuracy === false || $venueAccuracy === null) {
        $error = "Please capture the lecturer venue GPS before generating the QR code.";
    } elseif ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        $error = "The captured lecture venue coordinates are invalid. Please retry GPS.";
    } elseif (!$manualLocation && $venueAccuracy > $radius) {
        $error = "Venue GPS accuracy is too weak (" . round($venueAccuracy) . "m) for a " . $radius . "m radius. Increase the radius or retry GPS in a more open area.";
    } elseif (mysqli_num_rows($check_result) !== 1) {
        $error = "Please select one of your registered courses.";
    } else {
        $query = "INSERT INTO attendance_sessions
        (course_id, lecturer_id, session_token, expires_at, latitude, longitude, radius_meters)
        VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "iissddi", $course_id, $lecturer_id, $token, $expires_at, $latitude, $longitude, $radius);

        if (mysqli_stmt_execute($stmt)) {
            $sessionId = mysqli_insert_id($conn);
            audit_log($conn, "session_created", "Lecturer created an attendance session.", "attendance_session", $sessionId);
            $qr_link = app_base_url() . "/attendance/mark_attendance.php?token=" . urlencode($token);
            $course = mysqli_fetch_assoc($check_result);
            $createdSession = [
                "id" => $sessionId,
                "course_code" => $course["course_code"],
                "course_title" => $course["course_title"],
                "expires_at" => $expires_at,
                "duration_seconds" => $duration * 60,
                "radius_meters" => $radius,
                "marked_count" => 0,
            ];
        } else {
            $error = "Could not create attendance session.";
        }
    }
}

if (!$createdSession && !isset($_POST["create"])) {
    $active_query = "SELECT s.*, c.course_code, c.course_title, COUNT(ar.id) AS marked_count
                     FROM attendance_sessions s
                     JOIN courses c ON c.id = s.course_id
                     LEFT JOIN attendance_records ar ON ar.session_id = s.id
                     WHERE s.lecturer_id = ?
                       AND s.closed_at IS NULL
                       AND s.expires_at >= NOW()
                     GROUP BY s.id, c.course_code, c.course_title
                     ORDER BY s.created_at DESC
                     LIMIT 1";
    $active_stmt = mysqli_prepare($conn, $active_query);
    mysqli_stmt_bind_param($active_stmt, "i", $lecturer_id);
    mysqli_stmt_execute($active_stmt);
    $active_result = mysqli_stmt_get_result($active_stmt);
    $activeSession = mysqli_fetch_assoc($active_result);

    if ($activeSession) {
        $qr_link = app_base_url() . "/attendance/mark_attendance.php?token=" . urlencode($activeSession["session_token"]);
        $createdAt = strtotime($activeSession["created_at"]);
        $expiresAt = strtotime($activeSession["expires_at"]);
        $createdSession = [
            "id" => (int) $activeSession["id"],
            "course_code" => $activeSession["course_code"],
            "course_title" => $activeSession["course_title"],
            "expires_at" => $activeSession["expires_at"],
            "duration_seconds" => max(1, $expiresAt - $createdAt),
            "radius_meters" => (int) $activeSession["radius_meters"],
            "marked_count" => (int) $activeSession["marked_count"],
        ];
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Create Session</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=professional-ui-5">
</head>

<body class="session-page">

<div class="session-shell">

    <div class="session-hero">
        <div>
            <p class="section-kicker">Lecturer Tool</p>
            <h2>Create Attendance Session</h2>
            <p class="welcome">Generate a live QR code for students to scan, verify their face, and submit attendance.</p>
        </div>
        <a href="dashboard.php" class="button-link secondary-action">Back to Dashboard</a>
    </div>

    <?php if (isset($error)) { ?>
        <p class="alert alert-error"><?php echo e($error); ?></p>
    <?php } ?>

    <?php if ($flash) { ?>
        <p class="alert alert-<?php echo e($flash["type"]); ?>"><?php echo e($flash["message"]); ?></p>
    <?php } ?>

    <?php if (count($courses) === 0) { ?>
        <div class="empty-state">
            <h3>No Course Added Yet</h3>
            <p>You need to add at least one course before creating an attendance session.</p>
            <a href="create_course.php" class="button-link">Add Course</a>
        </div>
        <br>
        <a href="dashboard.php">Back to Dashboard</a>
    <?php } else { ?>

    <form method="POST" class="session-form-card" autocomplete="off">
        <?php render_csrf_input(); ?>

        <label>Select Course</label>
        <select name="course_id" required>
            <option value="">Select Course</option>

            <?php
            foreach ($courses as $row) {
                echo "<option value='" . e($row['id']) . "'>" .
                     e($row['course_code']) . " - " . e($row['course_title']) .
                     "</option>";
            }
            ?>
        </select>
        <p class="helper-text">If your course is not listed, add it from your lecturer dashboard first.</p>

        <label>Session Duration (minutes)</label>
        <input type="number" name="duration_minutes" min="1" max="180" value="10" required>

        <div class="venue-location-card">
            <div>
                <strong>Lecture Venue GPS</strong>
                <span id="venueLocationStatus">Capture your current location before generating the QR code.</span>
            </div>
            <button type="button" class="secondary-button" id="useLocation">Capture Venue GPS</button>
        </div>
        <button type="button" class="secondary-button compact-button" id="manualLocationButton">Enter Coordinates Manually</button>

        <label>Lecture Latitude</label>
        <input type="number" step="any" id="latitude" name="latitude" placeholder="Capture venue GPS" required readonly autocomplete="off">

        <label>Lecture Longitude</label>
        <input type="number" step="any" id="longitude" name="longitude" placeholder="Capture venue GPS" required readonly autocomplete="off">
        <input type="hidden" id="venueAccuracy" name="venue_accuracy">
        <input type="hidden" id="locationConfirmed" name="location_confirmed" value="0">
        <input type="hidden" id="manualLocation" name="manual_location" value="0">

        <label>Allowed Radius (meters)</label>
        <input type="number" name="radius_meters" min="10" max="1000" value="500" required autocomplete="off">
        <p class="helper-text">For indoor defense demos, 500 meters is safer because phone GPS can drift. For strict outdoor classes, use 100 meters.</p>

        <button type="submit" name="create" id="generateQrButton" disabled>Generate QR Code</button>

    </form>
    <?php } ?>

    <?php if ($createdSession && isset($qr_link)) { ?>

        <section class="live-session-card" id="liveSessionCard">
            <div class="live-session-header">
                <div>
                    <span class="status-badge status-active">QR Code Active</span>
                    <h3><?php echo e($createdSession["course_code"]); ?> - <?php echo e($createdSession["course_title"]); ?></h3>
                    <p>Students should scan this code during the active class session.</p>
                </div>
                <div class="live-session-tools">
                    <div class="marked-counter">
                        <strong id="markedCount"><?php echo number_format($createdSession["marked_count"]); ?></strong>
                        <span>Marked</span>
                    </div>
                    <button type="button" class="secondary-button compact-button" id="toggleSessionCard">Minimize</button>
                </div>
            </div>

            <div class="live-session-body" id="liveSessionBody">
            <div class="live-qr-layout">
                <div class="live-qr-frame">
                    <img src="../attendance/qr_code.php?data=<?php echo urlencode($qr_link); ?>" alt="Attendance session QR code">
                </div>

                <div class="live-session-details">
                    <div class="countdown-box">
                        <span>Expires in</span>
                        <strong id="countdownText">Loading...</strong>
                        <div class="session-progress">
                            <span id="sessionProgressBar"></span>
                        </div>
                    </div>

                    <div class="session-detail-grid">
                        <div>
                            <span>Expires At</span>
                            <strong id="expiresAtText"><?php echo e(date("g:i:s A", strtotime($createdSession["expires_at"]))); ?></strong>
                        </div>
                        <div>
                            <span>Allowed Radius</span>
                            <strong><?php echo e($createdSession["radius_meters"]); ?>m</strong>
                        </div>
                    </div>

                    <p class="link" id="qrLinkText"><?php echo e($qr_link); ?></p>

                    <div class="session-action-row">
                        <button type="button" class="secondary-button" id="fullscreenQr">View Fullscreen</button>
                        <button type="button" class="secondary-button" id="copyQrLink">Copy QR Link</button>
                    </div>

                    <div class="extend-session-panel">
                        <label for="extendMinutes">Extend active session</label>
                        <div class="extend-session-row">
                            <input type="number" id="extendMinutes" min="1" max="180" value="10">
                            <button type="button" class="secondary-button" id="extendSessionButton">Extend</button>
                        </div>
                        <p id="extendSessionStatus" class="status-text">Add more time without regenerating the QR code.</p>
                    </div>

                    <form method="POST" class="end-session-form">
                        <?php render_csrf_input(); ?>
                        <input type="hidden" name="session_id" value="<?php echo e($createdSession["id"]); ?>">
                        <button type="submit" name="end_created_session" class="danger-button">End Session</button>
                    </form>
                </div>
            </div>
            </div>
        </section>

    <?php } ?>

</div>

<script>
const useLocationButton = document.getElementById("useLocation");
const venueLocationStatus = document.getElementById("venueLocationStatus");
const venueAccuracy = document.getElementById("venueAccuracy");
const locationConfirmed = document.getElementById("locationConfirmed");
const manualLocation = document.getElementById("manualLocation");
const generateQrButton = document.getElementById("generateQrButton");
const manualLocationButton = document.getElementById("manualLocationButton");
const latitudeInput = document.getElementById("latitude");
const longitudeInput = document.getElementById("longitude");
if (useLocationButton) {
useLocationButton.addEventListener("click", function () {
    if (!navigator.geolocation) {
        venueLocationStatus.textContent = "GPS is not supported by this browser.";
        return;
    }

    venueLocationStatus.textContent = "Capturing best venue GPS reading. Please wait...";
    useLocationButton.disabled = true;

    captureBestPosition(function (position) {
        const accuracy = position.coords.accuracy || 0;
        latitudeInput.value = position.coords.latitude.toFixed(7);
        longitudeInput.value = position.coords.longitude.toFixed(7);
        venueAccuracy.value = accuracy;
        locationConfirmed.value = "1";
        manualLocation.value = "0";
        generateQrButton.disabled = false;
        venueLocationStatus.textContent = "Venue captured. Accuracy: about " + Math.round(accuracy) + " meters.";
        useLocationButton.textContent = "Retry Venue GPS";
        useLocationButton.disabled = false;
    }, function (error) {
        locationConfirmed.value = "0";
        generateQrButton.disabled = true;
        useLocationButton.disabled = false;

        if (error.code === error.PERMISSION_DENIED) {
            venueLocationStatus.textContent = "Location permission is blocked. Allow location for this site, then retry.";
            return;
        }

        if (error.code === error.POSITION_UNAVAILABLE) {
            venueLocationStatus.textContent = "Phone cannot get GPS right now. Turn Location on, move near a window/open area, or enter coordinates manually.";
            return;
        }

        if (error.code === error.TIMEOUT) {
            venueLocationStatus.textContent = "GPS timed out. Move near a window/open area and retry.";
            return;
        }

        venueLocationStatus.textContent = "Could not capture venue GPS. Retry or enter coordinates manually.";
    });
});
}

if (manualLocationButton) {
    manualLocationButton.addEventListener("click", function () {
        latitudeInput.readOnly = false;
        longitudeInput.readOnly = false;
        latitudeInput.placeholder = "Example: 7.7670178";
        longitudeInput.placeholder = "Example: 4.5981021";
        venueAccuracy.value = 0;
        locationConfirmed.value = "1";
        manualLocation.value = "1";
        generateQrButton.disabled = false;
        venueLocationStatus.textContent = "Manual coordinate mode enabled. Use coordinates from Google Maps at the lecture venue.";
    });
}

function captureBestPosition(onSuccess, onError) {
    let bestPosition = null;
    let finished = false;
    const options = { enableHighAccuracy: true, timeout: 30000, maximumAge: 0 };

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
            onError({ code: 0 });
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

    let watchId = null;
    try {
        watchId = navigator.geolocation.watchPosition(acceptPosition, function (error) {
            if (!bestPosition) {
                onError(error);
                finished = true;
            }
        }, options);
        window.setTimeout(finish, 10000);
    } catch (error) {
        navigator.geolocation.getCurrentPosition(onSuccess, onError, options);
    }
}

const liveSessionCard = document.getElementById("liveSessionCard");
const countdownText = document.getElementById("countdownText");
const sessionProgressBar = document.getElementById("sessionProgressBar");
const markedCount = document.getElementById("markedCount");
const copyQrLink = document.getElementById("copyQrLink");
const fullscreenQr = document.getElementById("fullscreenQr");
const qrLinkText = document.getElementById("qrLinkText");
const extendMinutes = document.getElementById("extendMinutes");
const extendSessionButton = document.getElementById("extendSessionButton");
const extendSessionStatus = document.getElementById("extendSessionStatus");
const toggleSessionCard = document.getElementById("toggleSessionCard");
const liveSessionBody = document.getElementById("liveSessionBody");
const expiresAtText = document.getElementById("expiresAtText");
const liveSessionCollapseKey = <?php echo json_encode($createdSession ? "live_session_card_collapsed_" . (int) $createdSession["id"] : ""); ?>;
let expiresAt = new Date(<?php echo json_encode($createdSession ? date("c", strtotime($createdSession["expires_at"])) : ""); ?>).getTime();
let durationSeconds = <?php echo (int) ($createdSession["duration_seconds"] ?? 0); ?>;
let countdownTimer = null;

function formatTime(date) {
    return date.toLocaleTimeString([], { hour: "numeric", minute: "2-digit", second: "2-digit" });
}

function setLiveSessionCollapsed(collapsed) {
    if (!liveSessionBody || !toggleSessionCard || !liveSessionCard) {
        return;
    }

    liveSessionBody.classList.toggle("is-collapsed", collapsed);
    liveSessionCard.classList.toggle("is-minimized", collapsed);
    toggleSessionCard.textContent = collapsed ? "Restore" : "Minimize";

    if (liveSessionCollapseKey) {
        localStorage.setItem(liveSessionCollapseKey, collapsed ? "1" : "0");
    }
}

if (toggleSessionCard && liveSessionBody) {
    setLiveSessionCollapsed(localStorage.getItem(liveSessionCollapseKey) === "1");
    toggleSessionCard.addEventListener("click", function () {
        setLiveSessionCollapsed(!liveSessionBody.classList.contains("is-collapsed"));
    });
}

function updateCountdown() {
    if (!liveSessionCard || !countdownText || !sessionProgressBar) {
        return;
    }

    const remainingSeconds = Math.max(0, Math.floor((expiresAt - Date.now()) / 1000));
    const minutes = Math.floor(remainingSeconds / 60);
    const seconds = remainingSeconds % 60;
    countdownText.textContent = minutes + "m " + String(seconds).padStart(2, "0") + "s";

    const percent = durationSeconds > 0 ? Math.max(0, Math.min(100, (remainingSeconds / durationSeconds) * 100)) : 0;
    sessionProgressBar.style.width = percent + "%";

    if (remainingSeconds <= 0) {
        countdownText.textContent = "Expired";
        liveSessionCard.classList.add("session-expired-card");
        if (countdownTimer) {
            window.clearInterval(countdownTimer);
            countdownTimer = null;
        }
    }
}

if (liveSessionCard && countdownText && sessionProgressBar) {
    updateCountdown();
    countdownTimer = window.setInterval(updateCountdown, 1000);
}

if (markedCount && liveSessionCard) {
    const sessionStatusUrl = <?php echo json_encode($createdSession ? with_context("attendance/session_status.php?session_id=" . (int) $createdSession["id"]) : ""); ?>;

    async function refreshSessionStatus() {
        if (!sessionStatusUrl) {
            return;
        }

        try {
            const response = await fetch(sessionStatusUrl, { cache: "no-store" });
            const data = await response.json();

            if (data.ok) {
                markedCount.textContent = data.marked_count;
                if (data.expires_at) {
                    expiresAt = new Date(data.expires_at).getTime();
                    durationSeconds = Math.max(durationSeconds, Math.floor((expiresAt - Date.now()) / 1000));
                    if (expiresAtText) {
                        expiresAtText.textContent = formatTime(new Date(expiresAt));
                    }
                    updateCountdown();
                }
                if (data.status === "expired" || data.status === "closed") {
                    liveSessionCard.classList.add("session-expired-card");
                } else {
                    liveSessionCard.classList.remove("session-expired-card");
                    if (!countdownTimer) {
                        countdownTimer = window.setInterval(updateCountdown, 1000);
                    }
                }
            }
        } catch (error) {
            return;
        }
    }

    refreshSessionStatus();
    window.setInterval(refreshSessionStatus, 5000);
}

if (extendSessionButton && extendMinutes) {
    extendSessionButton.addEventListener("click", async function () {
        const minutes = Math.max(1, Math.min(180, parseInt(extendMinutes.value || "10", 10)));
        const body = new URLSearchParams();
        body.set("session_id", <?php echo (int) ($createdSession["id"] ?? 0); ?>);
        body.set("minutes", minutes);
        body.set("csrf_token", <?php echo json_encode(csrf_token()); ?>);

        extendSessionButton.disabled = true;
        if (extendSessionStatus) {
            extendSessionStatus.textContent = "Extending session...";
        }

        try {
            const response = await fetch(<?php echo json_encode(with_context("attendance/session_extend.php")); ?>, {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: body.toString(),
                cache: "no-store"
            });
            const data = await response.json();

            if (!data.ok) {
                throw new Error(data.error || "Could not extend session.");
            }

            expiresAt = new Date(data.expires_at).getTime();
            durationSeconds = Math.max(durationSeconds, Math.floor((expiresAt - Date.now()) / 1000));
            if (expiresAtText) {
                expiresAtText.textContent = formatTime(new Date(expiresAt));
            }
            updateCountdown();
            liveSessionCard.classList.remove("session-expired-card");
            if (!countdownTimer) {
                countdownTimer = window.setInterval(updateCountdown, 1000);
            }

            if (extendSessionStatus) {
                extendSessionStatus.textContent = data.message;
            }
        } catch (error) {
            if (extendSessionStatus) {
                extendSessionStatus.textContent = error.message || "Could not extend session.";
            }
        } finally {
            extendSessionButton.disabled = false;
        }
    });
}

if (copyQrLink && qrLinkText) {
    copyQrLink.addEventListener("click", async function () {
        try {
            await navigator.clipboard.writeText(qrLinkText.textContent.trim());
            copyQrLink.textContent = "Copied";
            setTimeout(function () {
                copyQrLink.textContent = "Copy QR Link";
            }, 1600);
        } catch (error) {
            window.prompt("Copy QR link", qrLinkText.textContent.trim());
        }
    });
}

if (fullscreenQr && liveSessionCard) {
    fullscreenQr.addEventListener("click", function () {
        if (liveSessionCard.requestFullscreen) {
            liveSessionCard.requestFullscreen();
        }
    });
}
</script>

</body>

</html>
