<?php

$data = $_GET["data"] ?? "";

if ($data === "" || strlen($data) > 500) {
    http_response_code(400);
    exit("Invalid QR data.");
}

$node = getenv("NODE_BINARY") ?: "node";
$script = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "scripts" . DIRECTORY_SEPARATOR . "qr_generate.js";

function command_arg($value)
{
    return escapeshellarg($value);
}

$command = command_arg($node) . " " . command_arg($script) . " " . command_arg($data) . " 2>&1";
$svg = shell_exec($command);

if ($svg === null || strlen($svg) < 100 || strpos($svg, "<svg") === false) {
    $logDir = getenv("APP_LOG_DIR") ?: sys_get_temp_dir();
    if (!is_dir($logDir)) {
        mkdir($logDir, 0775, true);
    }
    file_put_contents($logDir . DIRECTORY_SEPARATOR . "qr_error.log", "Command: " . $command . PHP_EOL . "Output: " . (string) $svg . PHP_EOL);
    http_response_code(500);
    exit("Could not generate QR code.");
}

header("Content-Type: image/svg+xml");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
echo $svg;
