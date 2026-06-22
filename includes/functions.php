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

function csrf_token()
{
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = random_token(32);
    }

    return $_SESSION["csrf_token"];
}

function render_csrf_input()
{
    echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_token_is_valid()
{
    $token = $_POST["csrf_token"] ?? "";

    return is_string($token) &&
        isset($_SESSION["csrf_token"]) &&
        hash_equals($_SESSION["csrf_token"], $token);
}

function require_valid_csrf()
{
    if ($_SERVER["REQUEST_METHOD"] === "POST" && !csrf_token_is_valid()) {
        http_response_code(403);
        exit("Security check failed. Please refresh the page and try again.");
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
                parsed.searchParams.set("ctx", ctx);
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
            var input = form.querySelector('input[name="ctx"]');
            if (!input) {
                input = document.createElement("input");
                input.type = "hidden";
                input.name = "ctx";
                form.appendChild(input);
            }
            input.value = ctx;
        });
    })();
    </script>
    <?php
}

function render_theme_assets()
{
    foreach (headers_list() as $header) {
        if (stripos($header, "Location:") === 0) {
            return;
        }

        if (stripos($header, "Content-Type:") === 0 && stripos($header, "text/html") === false) {
            return;
        }
    }

    $scriptPath = rtrim(app_root_path(), "/") . "/assets/js/theme-toggle.js?v=1";
    ?>
    <script src="<?php echo e($scriptPath); ?>"></script>
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

function dashboard_icon($name)
{
    $icons = [
        "users" => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
        "book" => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5z"></path>',
        "calendar" => '<path d="M8 2v4"></path><path d="M16 2v4"></path><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M3 10h18"></path>',
        "activity" => '<path d="M22 12h-4l-3 8L9 4l-3 8H2"></path>',
        "check" => '<path d="M20 6 9 17l-5-5"></path>',
        "alert" => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path>',
        "percent" => '<path d="M19 5 5 19"></path><circle cx="6.5" cy="6.5" r="2.5"></circle><circle cx="17.5" cy="17.5" r="2.5"></circle>',
        "layers" => '<path d="m12 2 9 5-9 5-9-5 9-5z"></path><path d="m3 12 9 5 9-5"></path><path d="m3 17 9 5 9-5"></path>',
    ];

    $paths = $icons[$name] ?? $icons["activity"];

    return '<span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false">' . $paths . '</svg></span>';
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

function mail_configured()
{
    return brevo_api_configured() || smtp_mail_configured();
}

function smtp_mail_configured()
{
    return trim((string) getenv("MAIL_HOST")) !== "" &&
        trim((string) getenv("MAIL_USERNAME")) !== "" &&
        trim((string) getenv("MAIL_PASSWORD")) !== "" &&
        trim((string) getenv("MAIL_FROM_EMAIL")) !== "";
}

function brevo_api_configured()
{
    return trim((string) getenv("BREVO_API_KEY")) !== "" &&
        trim((string) getenv("MAIL_FROM_EMAIL")) !== "";
}

function missing_mail_variables($mode = "auto")
{
    if ($mode === "brevo_api") {
        $required = ["BREVO_API_KEY", "MAIL_FROM_EMAIL"];
    } elseif ($mode === "smtp") {
        $required = ["MAIL_HOST", "MAIL_USERNAME", "MAIL_PASSWORD", "MAIL_FROM_EMAIL"];
    } else {
        $required = ["BREVO_API_KEY", "MAIL_FROM_EMAIL"];
        if (!brevo_api_configured()) {
            $required = ["MAIL_HOST", "MAIL_USERNAME", "MAIL_PASSWORD", "MAIL_FROM_EMAIL"];
        }
    }

    $missing = [];

    foreach ($required as $key) {
        if (trim((string) getenv($key)) === "") {
            $missing[] = $key;
        }
    }

    return $missing;
}

function set_last_mail_error($message)
{
    $GLOBALS["last_mail_error"] = $message;

    if ($message !== "") {
        error_log("SmartAttend mail error: " . $message);
    }
}

function last_mail_error()
{
    return $GLOBALS["last_mail_error"] ?? "";
}

function smtp_read_response($socket)
{
    $response = "";

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === " ") {
            break;
        }
    }

    return $response;
}

function smtp_expect($socket, $codes)
{
    $response = smtp_read_response($socket);
    $GLOBALS["last_smtp_response"] = trim($response);
    $code = (int) substr($response, 0, 3);

    return in_array($code, (array) $codes, true);
}

function smtp_command($socket, $command, $codes)
{
    fwrite($socket, $command . "\r\n");

    return smtp_expect($socket, $codes);
}

function smtp_escape_message($message)
{
    $message = str_replace(["\r\n", "\r"], "\n", $message);
    $lines = explode("\n", $message);

    foreach ($lines as &$line) {
        if (str_starts_with($line, ".")) {
            $line = "." . $line;
        }
    }

    return implode("\r\n", $lines);
}

function send_app_email($toEmail, $toName, $subject, $htmlBody, $textBody = "")
{
    set_last_mail_error("");

    if (brevo_api_configured()) {
        return send_app_email_with_brevo_api($toEmail, $toName, $subject, $htmlBody, $textBody);
    }

    return send_app_email_with_smtp($toEmail, $toName, $subject, $htmlBody, $textBody);
}

function send_app_email_with_brevo_api($toEmail, $toName, $subject, $htmlBody, $textBody = "")
{
    set_last_mail_error("");

    if (!brevo_api_configured()) {
        set_last_mail_error("Missing Brevo API variables: " . implode(", ", missing_mail_variables("brevo_api")));
        return false;
    }

    if (!function_exists("curl_init")) {
        set_last_mail_error("PHP cURL extension is not available.");
        return false;
    }

    $fromEmail = trim((string) getenv("MAIL_FROM_EMAIL"));
    $fromName = trim((string) (getenv("MAIL_FROM_NAME") ?: "SmartAttend"));
    $apiKey = trim((string) getenv("BREVO_API_KEY"));

    if ($textBody === "") {
        $textBody = trim(strip_tags(str_replace(["<br>", "<br/>", "<br />"], "\n", $htmlBody)));
    }

    $payload = [
        "sender" => [
            "name" => $fromName,
            "email" => $fromEmail,
        ],
        "to" => [[
            "email" => $toEmail,
            "name" => trim((string) $toName) ?: $toEmail,
        ]],
        "subject" => $subject,
        "htmlContent" => $htmlBody,
        "textContent" => $textBody,
    ];

    $curl = curl_init("https://api.brevo.com/v3/smtp/email");
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            "accept: application/json",
            "api-key: " . $apiKey,
            "content-type: application/json",
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);

    $response = curl_exec($curl);
    $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($response === false) {
        set_last_mail_error("Brevo API request failed. " . $curlError);
        return false;
    }

    if ($statusCode < 200 || $statusCode >= 300) {
        $message = "Brevo API rejected the email with HTTP " . $statusCode . ".";
        $payload = json_decode($response, true);
        if (is_array($payload) && !empty($payload["message"])) {
            $message .= " " . $payload["message"];
        }
        set_last_mail_error($message);
        return false;
    }

    return true;
}

function send_app_email_with_smtp($toEmail, $toName, $subject, $htmlBody, $textBody = "")
{
    set_last_mail_error("");

    if (!smtp_mail_configured()) {
        set_last_mail_error("Missing SMTP mail variables: " . implode(", ", missing_mail_variables("smtp")));
        return false;
    }

    $host = trim((string) getenv("MAIL_HOST"));
    $port = (int) (getenv("MAIL_PORT") ?: 587);
    $username = trim((string) getenv("MAIL_USERNAME"));
    $password = (string) getenv("MAIL_PASSWORD");
    $fromEmail = trim((string) getenv("MAIL_FROM_EMAIL"));
    $fromName = trim((string) (getenv("MAIL_FROM_NAME") ?: "SmartAttend"));
    $toName = trim((string) $toName);
    $secure = strtolower(trim((string) (getenv("MAIL_ENCRYPTION") ?: "tls")));
    $transportHost = $secure === "ssl" ? "ssl://" . $host : $host;

    $socket = @fsockopen($transportHost, $port, $errno, $errstr, 20);
    if (!$socket) {
        set_last_mail_error("Could not connect to " . $host . ":" . $port . ". " . $errstr);
        return false;
    }

    stream_set_timeout($socket, 20);

    $serverName = $_SERVER["SERVER_NAME"] ?? "smartattend.local";
    $boundary = "smartattend_" . bin2hex(random_bytes(12));
    $safeSubject = str_replace(["\r", "\n"], "", $subject);
    $fromHeader = sprintf('"%s" <%s>', addcslashes($fromName, '"\\'), $fromEmail);
    $toHeader = $toName !== ""
        ? sprintf('"%s" <%s>', addcslashes($toName, '"\\'), $toEmail)
        : $toEmail;

    $headers = [
        "From: " . $fromHeader,
        "To: " . $toHeader,
        "Subject: " . $safeSubject,
        "MIME-Version: 1.0",
        "Content-Type: multipart/alternative; boundary=\"" . $boundary . "\"",
    ];

    if ($textBody === "") {
        $textBody = trim(strip_tags(str_replace(["<br>", "<br/>", "<br />"], "\n", $htmlBody)));
    }

    $message = implode("\r\n", $headers) . "\r\n\r\n";
    $message .= "--" . $boundary . "\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $textBody . "\r\n\r\n";
    $message .= "--" . $boundary . "\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $htmlBody . "\r\n\r\n";
    $message .= "--" . $boundary . "--";

    $fail = function ($message) use ($socket) {
        $response = $GLOBALS["last_smtp_response"] ?? "";
        set_last_mail_error($message . ($response !== "" ? " Server response: " . $response : ""));
        @smtp_command($socket, "QUIT", 221);
        fclose($socket);

        return false;
    };

    if (!smtp_expect($socket, 220)) {
        return $fail("SMTP server did not accept the connection.");
    }

    if (!smtp_command($socket, "EHLO " . $serverName, 250)) {
        return $fail("SMTP greeting failed.");
    }

    if ($secure === "tls") {
        if (!smtp_command($socket, "STARTTLS", 220)) {
            return $fail("SMTP TLS start failed.");
        }

        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            return $fail("SMTP TLS encryption could not be enabled.");
        }

        if (!smtp_command($socket, "EHLO " . $serverName, 250)) {
            return $fail("SMTP greeting after TLS failed.");
        }
    }

    if (!smtp_command($socket, "AUTH LOGIN", 334)) {
        return $fail("SMTP authentication was not accepted.");
    }

    if (!smtp_command($socket, base64_encode($username), 334)) {
        return $fail("SMTP username was rejected.");
    }

    if (!smtp_command($socket, base64_encode($password), 235)) {
        return $fail("SMTP password was rejected.");
    }

    if (!smtp_command($socket, "MAIL FROM:<" . $fromEmail . ">", 250)) {
        return $fail("SMTP sender email was rejected.");
    }

    if (!smtp_command($socket, "RCPT TO:<" . $toEmail . ">", [250, 251])) {
        return $fail("SMTP recipient email was rejected.");
    }

    if (!smtp_command($socket, "DATA", 354)) {
        return $fail("SMTP message body was not accepted.");
    }

    fwrite($socket, smtp_escape_message($message) . "\r\n.\r\n");
    if (!smtp_expect($socket, 250)) {
        return $fail("SMTP server rejected the final email message.");
    }

    smtp_command($socket, "QUIT", 221);
    fclose($socket);

    return true;
}

function send_password_reset_code_email($email, $name, $code)
{
    $safeCode = e($code);
    $safeName = e($name ?: "SmartAttend user");
    $subject = "Your SmartAttend password recovery code";
    $html = '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#17202a">'
        . '<h2 style="color:#003366">SmartAttend password recovery</h2>'
        . '<p>Hello ' . $safeName . ',</p>'
        . '<p>Use this verification code to reset your SmartAttend password:</p>'
        . '<p style="font-size:28px;font-weight:bold;letter-spacing:6px;color:#003366">' . $safeCode . '</p>'
        . '<p>This code expires in 10 minutes. If you did not request it, please ignore this email.</p>'
        . '</div>';
    $text = "Hello " . ($name ?: "SmartAttend user") . ",\n\n"
        . "Your SmartAttend password recovery code is: " . $code . "\n\n"
        . "This code expires in 10 minutes. If you did not request it, please ignore this email.";

    return send_app_email($email, $name, $subject, $html, $text);
}

sync_auth_context();
register_shutdown_function("render_theme_assets");
register_shutdown_function("render_tab_context_script");
