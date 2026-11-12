<?php
header("Content-Type: text/plain; charset=utf-8");
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Fill these in locally using your existing database connection settings.
$host = "localhost";
$user = "u902368618_db_chrisdavid";
$pwd  = "";
$db   = "u902368618_christydavid";

$conn = new mysqli($host, $user, $pwd, $db);
$conn->set_charset("utf8mb4");

function reply($code, $message) {
    http_response_code($code);
    exit($message);
}

$node = trim($_GET["node_name"] ?? "");
$rawTime = trim($_GET["time_received"] ?? "");
$distanceRaw = $_GET["distance_cm"] ?? "";
$lightRaw = $_GET["light_value"] ?? "";

// A timestamp is required. TimeAPI returns ISO format.
if (
    $rawTime === "" ||
    !preg_match(
        '/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})(?:\.\d+)?$/',
        $rawTime,
        $parts
    )
) {
    reply(400, "rejected: timestamp is required or invalid");
}

// The database column is DATETIME, so store whole seconds.
$sqlTime = $parts[1] . " " . $parts[2];
$date = DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", $sqlTime);
$dateErrors = DateTimeImmutable::getLastErrors();

if (
    !$date ||
    ($dateErrors !== false &&
        ($dateErrors["warning_count"] > 0 || $dateErrors["error_count"] > 0)) ||
    $date->format("Y-m-d H:i:s") !== $sqlTime
) {
    reply(400, "rejected: invalid timestamp");
}

if ($node === "" || !is_numeric($distanceRaw)) {
    reply(400, "rejected: node or distance is missing");
}

$distance = (float)$distanceRaw;
$light = filter_var($lightRaw, FILTER_VALIDATE_INT);

if (
    $distance < 0 || $distance > 400 ||
    $light === false || $light < 0 || $light > 1023
) {
    reply(400, "rejected: sensor value is invalid");
}

// Accept only registered nodes.
$check = $conn->prepare(
    "SELECT node_name FROM sensor_register WHERE node_name = ?"
);
$check->bind_param("s", $node);
$check->execute();
$check->store_result();

if ($check->num_rows === 0) {
    reply(403, "rejected: node is not registered");
}

try {
    $conn->begin_transaction();

    // Store the distance and light readings. Temperature/humidity are NULL.
    $insert = $conn->prepare(
        "INSERT INTO sensor_data
         (node_name, time_received, temperature, humidity, distance_cm, light_value)
         VALUES (?, ?, NULL, NULL, ?, ?)"
    );
    $insert->bind_param("ssdi", $node, $sqlTime, $distance, $light);
    $insert->execute();

    // Increment only after a new measurement is inserted.
    $activity = $conn->prepare(
        "INSERT INTO sensor_activity (node_name, activity_count)
         VALUES (?, 1)
         ON DUPLICATE KEY UPDATE activity_count = activity_count + 1"
    );
    $activity->bind_param("s", $node);
    $activity->execute();

    $conn->commit();
    reply(201, "stored");

} catch (mysqli_sql_exception $e) {
    $conn->rollback();

    // A repeated node/timestamp is already delivered; do not increment the count.
    if ((int)$e->getCode() === 1062) {
        reply(200, "already_stored");
    }

    reply(500, "database error");
}
?>
