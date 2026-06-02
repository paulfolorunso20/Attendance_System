<?php

function migrations_enabled()
{
    $value = strtolower(trim((string) (getenv("RUN_MIGRATIONS") ?: "true")));
    return !in_array($value, ["0", "false", "no", "off"], true);
}

function ensure_migrations_table($conn)
{
    $query = "CREATE TABLE IF NOT EXISTS schema_migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(190) NOT NULL UNIQUE,
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!mysqli_query($conn, $query)) {
        throw new RuntimeException("Could not create schema_migrations table: " . mysqli_error($conn));
    }
}

function applied_migrations($conn)
{
    $applied = [];
    $result = mysqli_query($conn, "SELECT migration FROM schema_migrations");

    if (!$result) {
        return $applied;
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $applied[$row["migration"]] = true;
    }

    return $applied;
}

function migration_statements($sql)
{
    $statements = [];
    $parts = explode(";", $sql);

    foreach ($parts as $part) {
        $statement = trim($part);
        if ($statement !== "") {
            $statements[] = $statement;
        }
    }

    return $statements;
}

function run_migration_file($conn, $file)
{
    $sql = file_get_contents($file);

    if ($sql === false) {
        throw new RuntimeException("Could not read migration file: " . basename($file));
    }

    mysqli_begin_transaction($conn);

    try {
        foreach (migration_statements($sql) as $statement) {
            if (!mysqli_query($conn, $statement)) {
                throw new RuntimeException("Migration statement failed in " . basename($file) . ": " . mysqli_error($conn));
            }
        }

        $migrationName = basename($file);
        $stmt = mysqli_prepare($conn, "INSERT INTO schema_migrations (migration) VALUES (?)");
        mysqli_stmt_bind_param($stmt, "s", $migrationName);
        mysqli_stmt_execute($stmt);

        mysqli_commit($conn);
    } catch (Throwable $exception) {
        mysqli_rollback($conn);
        throw $exception;
    }
}

function seed_initial_admin($conn)
{
    $adminEmail = trim((string) (getenv("ADMIN_EMAIL") ?: ""));
    $adminPassword = (string) (getenv("ADMIN_PASSWORD") ?: "");
    $adminName = trim((string) (getenv("ADMIN_NAME") ?: "System Admin"));

    if ($adminEmail === "" || $adminPassword === "") {
        return;
    }

    $count = 0;
    $result = mysqli_query($conn, "SELECT COUNT(*) FROM users WHERE role = 'admin'");
    if ($result) {
        $count = (int) mysqli_fetch_row($result)[0];
    }

    if ($count > 0) {
        return;
    }

    $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
    $role = "admin";
    $stmt = mysqli_prepare($conn, "INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ssss", $adminName, $adminEmail, $passwordHash, $role);
    mysqli_stmt_execute($stmt);
}

function run_database_migrations($conn)
{
    if (!migrations_enabled()) {
        return;
    }

    ensure_migrations_table($conn);

    $migrationsPath = APP_ROOT . DIRECTORY_SEPARATOR . "database" . DIRECTORY_SEPARATOR . "migrations";
    if (!is_dir($migrationsPath)) {
        return;
    }

    $files = glob($migrationsPath . DIRECTORY_SEPARATOR . "*.sql") ?: [];
    sort($files, SORT_STRING);

    $applied = applied_migrations($conn);

    foreach ($files as $file) {
        $migrationName = basename($file);
        if (!isset($applied[$migrationName])) {
            run_migration_file($conn, $file);
            $applied[$migrationName] = true;
        }
    }

    seed_initial_admin($conn);
}
