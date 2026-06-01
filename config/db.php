<?php

$host = getenv("DB_HOST") ?: getenv("MYSQLHOST") ?: "localhost";
$port = (int) (getenv("DB_PORT") ?: getenv("MYSQLPORT") ?: 3306);
$user = getenv("DB_USERNAME") ?: getenv("MYSQLUSER") ?: "root";
$password = getenv("DB_PASSWORD") ?: getenv("MYSQLPASSWORD") ?: "";
$database = getenv("DB_DATABASE") ?: getenv("MYSQLDATABASE") ?: "attendance system_mysql";

$conn = mysqli_connect($host, $user, $password, $database, $port);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

$timezone = getenv("APP_TIMEZONE") ?: "Africa/Lagos";
date_default_timezone_set($timezone);
