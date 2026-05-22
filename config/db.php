<?php

$host = "localhost";
$user = "root";
$password = "";
$database = "attendance system_mysql";

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

date_default_timezone_set("Africa/Lagos");
