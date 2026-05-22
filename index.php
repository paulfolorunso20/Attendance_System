<?php
session_start();
include "includes/functions.php";

if (isset($_SESSION["role"]) && $_SESSION["role"] === "lecturer") {
    redirect_with_context("lecturer_dashboard.php");
}

if (isset($_SESSION["role"]) && $_SESSION["role"] === "student") {
    redirect_with_context("student_dashboard.php");
}

if (isset($_SESSION["role"]) && $_SESSION["role"] === "admin") {
    redirect_with_context("admin_dashboard.php");
}

redirect_with_context("login.php");
