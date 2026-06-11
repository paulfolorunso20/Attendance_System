<?php

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function auth_context_from_request()
{
    $context = $_POST["ctx"] ?? $_GET["ctx"] ?? "";
    $context = is_string($context) ? trim($context) : "";

    return preg_match('/^[a-f0-9]{32}$/', $context) ? $context : "";
}

function apply_auth_context($context, $auth)
{
    $_SESSION["auth_context"] = $context;
    $_SESSION["user_id"] = (int) $auth["user_id"];
    $_SESSION["full_name"] = $auth["full_name"] ?? "";
    $_SESSION["title"] = $auth["title"] ?? "";
    $_SESSION["position"] = $auth["position"] ?? "";
    $_SESSION["role"] = trim($auth["role"] ?? "");
}

function sync_auth_context()
{
    $context = auth_context_from_request();

    if ($context !== "" && isset($_SESSION["auth_contexts"][$context])) {
        apply_auth_context($context, $_SESSION["auth_contexts"][$context]);
        return $context;
    }

    return $_SESSION["auth_context"] ?? "";
}

function create_auth_context($user)
{
    $context = random_token(16);

    $_SESSION["auth_contexts"][$context] = [
        "user_id" => (int) $user["id"],
        "full_name" => $user["full_name"] ?? "",
        "title" => $user["title"] ?? "",
        "position" => $user["position"] ?? "",
        "role" => trim($user["role"] ?? ""),
    ];

    apply_auth_context($context, $_SESSION["auth_contexts"][$context]);

    return $context;
}

function current_auth_context()
{
    if (!empty($_SESSION["auth_context"])) {
        return $_SESSION["auth_context"];
    }

    $context = auth_context_from_request();

    if ($context !== "") {
        return $context;
    }

    return $_SESSION["auth_context"] ?? "";
}

function app_root_path()
{
    $configuredPath = trim((string) (getenv("APP_BASE_PATH") ?: ""));

    if ($configuredPath !== "") {
        return "/" . trim($configuredPath, "/");
    }

    $script = str_replace("\\", "/", $_SERVER["SCRIPT_NAME"] ?? "");
    $marker = "/attendance-system";
    $markerPosition = strpos($script, $marker);

    if ($markerPosition !== false) {
        return substr($script, 0, $markerPosition + strlen($marker));
    }

    return "";
}

function with_context($url)
{
    $context = current_auth_context();

    if (strpos($url, "ctx=") !== false || preg_match('/^(https?:|mailto:|tel:|#|javascript:)/i', $url)) {
        return $url;
    }

    $fragment = "";
    $hashPosition = strpos($url, "#");
    if ($hashPosition !== false) {
        $fragment = substr($url, $hashPosition);
        $url = substr($url, 0, $hashPosition);
    }

    if ($url !== "" && $url[0] !== "/" && strpos($url, "/") !== false) {
        $url = rtrim(app_root_path(), "/") . "/" . ltrim($url, "/");
    }

    if ($context === "") {
        return $url . $fragment;
    }

    $separator = strpos($url, "?") === false ? "?" : "&";
    return $url . $separator . "ctx=" . urlencode($context) . $fragment;
}

function redirect_with_context($url)
{
    header("Location: " . with_context($url));
    exit();
}

function render_context_input()
{
    $context = current_auth_context();

    if ($context !== "") {
        echo '<input type="hidden" name="ctx" value="' . e($context) . '">';
    }
}

function render_tab_context_script()
{
    foreach (headers_list() as $header) {
        if (stripos($header, "Location:") === 0) {
            return;
        }

        if (stripos($header, "Content-Type:") === 0 && stripos($header, "text/html") === false) {
            return;
        }
    }

    $context = current_auth_context();

    if ($context === "") {
        return;
    }
    ?>
    <script>
    (function () {
        var ctx = <?php echo json_encode($context); ?>;
        var addContext = function (url) {
            try {
                var parsed = new URL(url, window.location.href);
                if (parsed.origin !== window.location.origin) {
                    return url;
                }
                if (!parsed.searchParams.has("ctx")) {
                    parsed.searchParams.set("ctx", ctx);
                }
                return parsed.pathname + parsed.search + parsed.hash;
            } catch (error) {
                return url;
            }
        };

        if (!new URLSearchParams(window.location.search).has("ctx")) {
            window.history.replaceState(null, "", addContext(window.location.href));
        }

        document.querySelectorAll("a[href]").forEach(function (link) {
            var href = link.getAttribute("href");
            if (href && !/^(https?:|mailto:|tel:|#|javascript:)/i.test(href)) {
                link.setAttribute("href", addContext(href));
            }
        });

        document.querySelectorAll("form").forEach(function (form) {
            form.setAttribute("action", addContext(form.getAttribute("action") || window.location.href));
            if (!form.querySelector('input[name="ctx"]')) {
                var input = document.createElement("input");
                input.type = "hidden";
                input.name = "ctx";
                input.value = ctx;
                form.appendChild(input);
            }
        });
    })();
    </script>
    <?php
}

function require_role($role)
{
    sync_auth_context();

    if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== $role) {
        redirect_with_context("auth/login.php");
    }
}

function set_flash($type, $message)
{
    $_SESSION["flash"] = [
        "type" => $type,
        "message" => $message,
    ];
}

function audit_log($conn, $action, $description, $entityType = null, $entityId = null, $userId = null, $userRole = null)
{
    if (!$conn) {
        return;
    }

    $userId = $userId === null ? current_user_id() : (int) $userId;
    $userIdValue = $userId > 0 ? $userId : null;
    $userRoleValue = $userRole === null ? ($_SESSION["role"] ?? null) : $userRole;
    $entityIdValue = $entityId === null ? null : (int) $entityId;
    $ipAddress = $_SERVER["REMOTE_ADDR"] ?? null;
    $userAgent = substr($_SERVER["HTTP_USER_AGENT"] ?? "", 0, 255);

    $query = "INSERT INTO audit_logs
        (user_id, user_role, action, entity_type, entity_id, description, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);

    if (!$stmt) {
        return;
    }

    mysqli_stmt_bind_param($stmt, "isssisss", $userIdValue, $userRoleValue, $action, $entityType, $entityIdValue, $description, $ipAddress, $userAgent);

    try {
        mysqli_stmt_execute($stmt);
    } catch (mysqli_sql_exception $exception) {
        return;
    }
}

function get_flash()
{
    $flash = $_SESSION["flash"] ?? null;
    unset($_SESSION["flash"]);
    return $flash;
}

function current_user_id()
{
    sync_auth_context();

    return (int) ($_SESSION["user_id"] ?? 0);
}

function department_options()
{
    return [
        "Computer Science",
        "Software Engineering",
        "Cybersecurity",
        "Data Science",
        "Information Technology",
        "Information Systems",
        "Library and Information Science",
    ];
}

function render_department_options($selectedDepartment = "")
{
    foreach (department_options() as $department) {
        $selected = $department === $selectedDepartment ? " selected" : "";
        echo "<option value=\"" . e($department) . "\"" . $selected . ">" . e($department) . "</option>";
    }
}

function is_valid_matric_no($matricNo)
{
    return is_string($matricNo) && preg_match('/^\d{4}\/\d{5}$/', trim($matricNo)) === 1;
}

function redirect_for_role($role)
{
    if ($role === "admin") {
        redirect_with_context("admin/dashboard.php");
    }

    if ($role === "lecturer") {
        redirect_with_context("lecturer/dashboard.php");
    }

    if ($role === "student") {
        if (!empty($_SESSION['pending_attendance_token'])) {
            $pendingToken = $_SESSION['pending_attendance_token'];
            unset($_SESSION['pending_attendance_token']);
            redirect_with_context("attendance/mark_attendance.php?token=" . urlencode($pendingToken));
        }

        redirect_with_context("student/dashboard.php");
    }
}

function time_greeting()
{
    $hour = (int) date("G");

    if ($hour < 12) {
        return "Good Morning";
    }

    if ($hour < 17) {
        return "Good Afternoon";
    }

    if ($hour < 21) {
        return "Good Evening";
    }

    return "Good Day";
}

function lecturer_display_name($title, $position, $fullName)
{
    $title = trim((string) $title);
    $position = trim((string) $position);
    $fullName = trim((string) $fullName);

    if ($position === "Prof") {
        return trim("Prof. " . $fullName);
    }

    if ($position === "Dr") {
        return trim("Dr. " . $fullName);
    }

    if ($position === "HOD") {
        return trim("HOD " . $fullName);
    }

    return trim($title . " " . $fullName);
}

function app_base_url()
{
    $publicBaseUrl = trim((string) (getenv("APP_BASE_URL") ?: ""));
    $https = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ||
        (($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "") === "https");
    $scheme = $https ? "https" : "http";
    $host = $_SERVER["HTTP_HOST"] ?? "localhost";
    $path = app_root_path();

    if ($publicBaseUrl !== "") {
        $configuredHost = parse_url($publicBaseUrl, PHP_URL_HOST);
        $requestHost = explode(":", $host)[0];
        $configuredIsLocal = in_array($configuredHost, ["localhost", "127.0.0.1", "::1"], true);
        $requestIsLocal = in_array($requestHost, ["localhost", "127.0.0.1", "::1"], true);

        if (!$configuredIsLocal || $requestIsLocal) {
            return rtrim($publicBaseUrl, "/") . ($path ? $path : "");
        }
    }

    return $scheme . "://" . $host . ($path ? $path : "");
}

function media_url($path)
{
    $path = trim((string) $path);

    if ($path === "") {
        return "";
    }

    if (preg_match('/^(https?:)?\/\//i', $path) || str_starts_with($path, "data:")) {
        return $path;
    }

    return rtrim(app_root_path(), "/") . "/" . ltrim($path, "/");
}

function random_token($length = 32)
{
    return bin2hex(random_bytes($length));
}

function distance_in_meters($lat1, $lng1, $lat2, $lng2)
{
    $earthRadius = 6371000;
    $latFrom = deg2rad((float) $lat1);
    $latTo = deg2rad((float) $lat2);
    $latDelta = deg2rad((float) $lat2 - (float) $lat1);
    $lngDelta = deg2rad((float) $lng2 - (float) $lng1);

    $angle = sin($latDelta / 2) * sin($latDelta / 2) +
        cos($latFrom) * cos($latTo) *
        sin($lngDelta / 2) * sin($lngDelta / 2);

    return $earthRadius * (2 * atan2(sqrt($angle), sqrt(1 - $angle)));
}

function is_valid_face_descriptor($descriptor)
{
    return is_string($descriptor) && preg_match('/^[a-f0-9]{64}$/', $descriptor) === 1;
}

function descriptor_distance($first, $second)
{
    if (!is_valid_face_descriptor($first) || !is_valid_face_descriptor($second)) {
        return 256;
    }

    $distance = 0;

    for ($i = 0; $i < 64; $i++) {
        $xor = hexdec($first[$i]) ^ hexdec($second[$i]);
        while ($xor > 0) {
            $distance += $xor & 1;
            $xor >>= 1;
        }
    }

    return $distance;
}

function face_descriptor_matches($registeredDescriptor, $capturedDescriptor)
{
    return descriptor_distance($registeredDescriptor, $capturedDescriptor) <= 95;
}

function cloudinary_configured()
{
    return trim((string) getenv("CLOUDINARY_CLOUD_NAME")) !== "" &&
        trim((string) getenv("CLOUDINARY_API_KEY")) !== "" &&
        trim((string) getenv("CLOUDINARY_API_SECRET")) !== "";
}

function upload_image_to_cloud($bytes, $fileName, $folder, $mimeType = "image/jpeg")
{
    if (!cloudinary_configured() || !function_exists("curl_init")) {
        return null;
    }

    $cloudName = trim((string) getenv("CLOUDINARY_CLOUD_NAME"));
    $apiKey = trim((string) getenv("CLOUDINARY_API_KEY"));
    $apiSecret = trim((string) getenv("CLOUDINARY_API_SECRET"));
    $folder = trim((string) $folder);
    $timestamp = time();
    $publicId = pathinfo($fileName, PATHINFO_FILENAME);

    $signaturePayload = [
        "folder" => $folder,
        "public_id" => $publicId,
        "timestamp" => $timestamp,
    ];
    ksort($signaturePayload);

    $signatureBase = [];
    foreach ($signaturePayload as $key => $value) {
        $signatureBase[] = $key . "=" . $value;
    }
    $signature = sha1(implode("&", $signatureBase) . $apiSecret);

    $temporaryFile = tempnam(sys_get_temp_dir(), "face_");
    if ($temporaryFile === false || file_put_contents($temporaryFile, $bytes) === false) {
        return null;
    }

    $curl = curl_init("https://api.cloudinary.com/v1_1/" . rawurlencode($cloudName) . "/image/upload");
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_POSTFIELDS => [
            "file" => curl_file_create($temporaryFile, $mimeType, $fileName),
            "api_key" => $apiKey,
            "timestamp" => $timestamp,
            "folder" => $folder,
            "public_id" => $publicId,
            "signature" => $signature,
        ],
    ]);

    $response = curl_exec($curl);
    $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    unlink($temporaryFile);

    if ($response === false || $statusCode < 200 || $statusCode >= 300) {
        return null;
    }

    $payload = json_decode($response, true);

    return is_array($payload) && !empty($payload["secure_url"]) ? $payload["secure_url"] : null;
}

function upload_face_snapshot_to_cloud($bytes, $fileName)
{
    return upload_image_to_cloud($bytes, $fileName, getenv("CLOUDINARY_FOLDER") ?: "smartattend/faces", "image/jpeg");
}

function save_face_snapshot_locally($bytes, $fileName)
{
    // Set UPLOAD_DIR to a mounted Railway Volume path later if snapshots must persist across redeploys.
    $dir = getenv("UPLOAD_DIR") ?: (__DIR__ . "/../uploads/faces");
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $path = $dir . "/" . $fileName;

    if (file_put_contents($path, $bytes) === false) {
        return null;
    }

    return "uploads/faces/" . $fileName;
}

function save_face_snapshot($studentId, $sessionId, $imageData)
{
    if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $imageData)) {
        return null;
    }

    $parts = explode(",", $imageData, 2);
    if (count($parts) !== 2) {
        return null;
    }

    $bytes = base64_decode($parts[1], true);
    if ($bytes === false || strlen($bytes) < 1024 || strlen($bytes) > 5 * 1024 * 1024) {
        return null;
    }

    $fileName = "student_" . (int) $studentId . "_session_" . (int) $sessionId . "_" . time() . ".jpg";

    if (cloudinary_configured()) {
        return upload_face_snapshot_to_cloud($bytes, $fileName);
    }

    return save_face_snapshot_locally($bytes, $fileName);
}

function save_profile_image_locally($bytes, $fileName)
{
    $dir = getenv("PROFILE_UPLOAD_DIR") ?: (__DIR__ . "/../uploads/profiles");
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $path = $dir . "/" . $fileName;

    if (file_put_contents($path, $bytes) === false) {
        return null;
    }

    return "uploads/profiles/" . $fileName;
}

function save_profile_image_bytes($userId, $bytes, $mimeType)
{
    if (!in_array($mimeType, ["image/jpeg", "image/png", "image/webp"], true)) {
        return null;
    }

    if (!is_string($bytes) || strlen($bytes) < 1024 || strlen($bytes) > 3 * 1024 * 1024) {
        return null;
    }

    $extension = match ($mimeType) {
        "image/png" => "png",
        "image/webp" => "webp",
        default => "jpg",
    };
    $fileName = "user_" . (int) $userId . "_profile_" . time() . "." . $extension;

    if (cloudinary_configured()) {
        $cloudPath = upload_image_to_cloud($bytes, $fileName, getenv("CLOUDINARY_PROFILE_FOLDER") ?: "smartattend/profiles", $mimeType);
        if ($cloudPath !== null) {
            return $cloudPath;
        }
    }

    return save_profile_image_locally($bytes, $fileName);
}

function save_profile_image_data($userId, $imageData)
{
    if (!is_string($imageData) || !preg_match('/^data:image\/(png|jpeg|jpg|webp);base64,/', $imageData, $matches)) {
        return null;
    }

    $parts = explode(",", $imageData, 2);
    if (count($parts) !== 2) {
        return null;
    }

    $bytes = base64_decode($parts[1], true);
    if ($bytes === false) {
        return null;
    }

    $mimeType = "image/" . ($matches[1] === "jpg" ? "jpeg" : $matches[1]);

    return save_profile_image_bytes($userId, $bytes, $mimeType);
}

function save_profile_image_upload($userId, $file)
{
    if (!is_array($file) || ($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $size = (int) ($file["size"] ?? 0);
    if ($size < 1024 || $size > 3 * 1024 * 1024) {
        return null;
    }

    $temporaryPath = $file["tmp_name"] ?? "";
    $imageInfo = is_uploaded_file($temporaryPath) ? getimagesize($temporaryPath) : false;
    if ($imageInfo === false || !in_array($imageInfo["mime"], ["image/jpeg", "image/png", "image/webp"], true)) {
        return null;
    }

    $bytes = file_get_contents($temporaryPath);
    if ($bytes === false) {
        return null;
    }

    return save_profile_image_bytes($userId, $bytes, $imageInfo["mime"]);
}

sync_auth_context();
register_shutdown_function("render_tab_context_script");
