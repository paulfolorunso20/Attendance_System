<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define("APP_ROOT", dirname(__DIR__));
define("APP_CONFIG_PATH", APP_ROOT . DIRECTORY_SEPARATOR . "config");
define("APP_INCLUDES_PATH", APP_ROOT . DIRECTORY_SEPARATOR . "includes");

require_once APP_CONFIG_PATH . DIRECTORY_SEPARATOR . "db.php";
require_once APP_CONFIG_PATH . DIRECTORY_SEPARATOR . "app.php";
require_once APP_INCLUDES_PATH . DIRECTORY_SEPARATOR . "migrations.php";

run_database_migrations($conn);

require_once APP_INCLUDES_PATH . DIRECTORY_SEPARATOR . "functions.php";

$scriptName = basename($_SERVER["SCRIPT_NAME"] ?? "");
if ($scriptName !== "index.php" && !headers_sent()) {
    header("X-Robots-Tag: noindex, nofollow", false);
}
