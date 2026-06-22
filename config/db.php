<?php

function load_local_env($path)
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === "" || strpos($line, "#") === 0 || strpos($line, "=") === false) {
            continue;
        }

        [$key, $value] = explode("=", $line, 2);
        $key = trim($key);
        $value = trim($value);

        if ($key === "" || getenv($key) !== false) {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        putenv($key . "=" . $value);
        $_ENV[$key] = $value;
    }
}

load_local_env(dirname(__DIR__) . DIRECTORY_SEPARATOR . ".env");

$databaseUrl = getenv("DATABASE_URL") ?: getenv("MYSQL_URL") ?: "";
$parsedDatabaseUrl = $databaseUrl !== "" ? parse_url($databaseUrl) : false;

if (is_array($parsedDatabaseUrl)) {
    $host = $parsedDatabaseUrl["host"] ?? "localhost";
    $port = (int) ($parsedDatabaseUrl["port"] ?? 3306);
    $user = isset($parsedDatabaseUrl["user"]) ? rawurldecode($parsedDatabaseUrl["user"]) : "root";
    $password = isset($parsedDatabaseUrl["pass"]) ? rawurldecode($parsedDatabaseUrl["pass"]) : "";
    $database = isset($parsedDatabaseUrl["path"]) ? ltrim(rawurldecode($parsedDatabaseUrl["path"]), "/") : "";
} else {
    $host = getenv("DB_HOST") ?: getenv("MYSQLHOST") ?: "localhost";
    $port = (int) (getenv("DB_PORT") ?: getenv("MYSQLPORT") ?: 3306);
    $user = getenv("DB_USERNAME") ?: getenv("MYSQLUSER") ?: "root";
    $password = getenv("DB_PASSWORD") ?: getenv("MYSQLPASSWORD") ?: "";
    $database = getenv("DB_DATABASE") ?: getenv("MYSQLDATABASE") ?: "attendance system_mysql";
}

$conn = mysqli_connect($host, $user, $password, $database, $port);

if (!$conn) {
    http_response_code(503);
    die("The system is temporarily unable to connect to the database. Please try again shortly.");
}

mysqli_set_charset($conn, "utf8mb4");

$timezone = getenv("APP_TIMEZONE") ?: "Africa/Lagos";
date_default_timezone_set($timezone);
