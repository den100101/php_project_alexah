
<?php

// Load .env manually for local development.
$envFile = __DIR__ . "/.env";

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === "" || str_starts_with($line, "#") || !str_contains($line, "=")) {
            continue;
        }

        [$key, $value] = explode("=", $line, 2);
        $key = trim($key);
        $value = trim($value);

        if (
            strlen($value) >= 2 &&
            (($value[0] === '"' && substr($value, -1) === '"') ||
                ($value[0] === "'" && substr($value, -1) === "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if ($key !== "") {
            $_ENV[$key] = $value;
        }
    }
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = $_ENV["DB_HOST"] ?? "localhost";
$user = $_ENV["DB_USER"] ?? "root";
$password = $_ENV["DB_PASSWORD"] ?? "";
$database = $_ENV["DB_NAME"] ?? "db_website";
$port = (int) ($_ENV["DB_PORT"] ?? 3306);

try {
    $conn = mysqli_init();

    if ($host !== "localhost" && $host !== "127.0.0.1") {
        $caPath = __DIR__ . "/certs/ca.pem";

        if (!file_exists($caPath)) {
            throw new RuntimeException("Aiven CA certificate not found.");
        }

        $conn->ssl_set(null, null, $caPath, null, null);
    }

    $conn->real_connect(
        $host,
        $user,
        $password,
        $database,
        $port,
        null,
        ($host !== "localhost" && $host !== "127.0.0.1")
            ? MYSQLI_CLIENT_SSL
            : 0
    );

    $conn->set_charset("utf8mb4");
} catch (Throwable $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    exit("Database connection failed. Check the server configuration.");
}
