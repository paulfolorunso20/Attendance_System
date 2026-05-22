<?php

$data = $_GET["data"] ?? "";

if ($data === "" || strlen($data) > 500) {
    http_response_code(400);
    exit("Invalid QR data.");
}

$node = "C:\\PROGRA~1\\nodejs\\node.exe";
$script = __DIR__ . DIRECTORY_SEPARATOR . "qr_generate.js";

function windows_arg($value)
{
    return '"' . str_replace('"', '\"', $value) . '"';
}

$command = windows_arg($node) . " " . windows_arg($script) . " " . windows_arg($data) . " 2>&1";
$svg = shell_exec($command);

if ($svg === null || strlen($svg) < 100 || strpos($svg, "<svg") === false) {
    file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . "qr_error.log", "Command: " . $command . PHP_EOL . "Output: " . (string) $svg . PHP_EOL);
    http_response_code(500);
    exit("Could not generate QR code.");
}

header("Content-Type: image/svg+xml");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
echo $svg;
