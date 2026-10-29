<?php
header("Content-Type: text/plain; charset=utf-8");

$host = "localhost";
$user = "u902368618_db_chrisdavid";
$pwd = "Saleth@18";
$db = "u902368618_christydavid";

$conn = new mysqli($host, $user, $pwd, $db);

if ($conn->connect_error) {
    die("Database connection failed");
}

// The whole part after ? is the Base64 message
$encodedMessage = rawurldecode($_SERVER["QUERY_STRING"]);
$message = base64_decode($encodedMessage, true);

if ($message === false) {
    die("Data rejected: invalid Base64 message");
}

// Turn the decoded text into named values
parse_str($message, $values);

$node = $values["nodeId"] ?? "";
$temp = $values["nodeTemp"] ?? "";
$humidity = $values["nodeHumidity"] ?? "";
$time = $values["timeReceived"] ?? "";

if ($node === "" || $temp === "" || $humidity === "" || $time === "") {
    die("Data rejected: node, temperature, humidity, and time are required");
}

if (!is_numeric($temp) || $temp < -10 || $temp > 100) {
    die("Data rejected: temperature must be from -10 to 100");
}

if (!is_numeric($humidity) || $humidity < 0 || $humidity > 100) {
    die("Data rejected: humidity must be from 0 to 100");
}

// The encoded time must use YYYY-MM-DD HH:MM:SS
$date = DateTime::createFromFormat("!Y-m-d H:i:s", $time);

if (!$date || $date->format("Y-m-d H:i:s") !== $time) {
    die("Data rejected: time must use YYYY-MM-DD HH:MM:SS");
}

$sqlTime = $date->format("Y-m-d H:i:s");

// Check that this node is registered
$check = $conn->prepare(
    "SELECT node_name FROM sensor_register WHERE node_name = ?"
);
$check->bind_param("s", $node);
$check->execute();
$check->store_result();

if ($check->num_rows == 0) {
    die("Data rejected: node is not registered");
}

// Insert the decoded values
$insert = $conn->prepare(
    "INSERT INTO sensor_data
     (node_name, time_received, temperature, humidity)
     VALUES (?, ?, ?, ?)"
);
$insert->bind_param("ssdd", $node, $sqlTime, $temp, $humidity);

try {
    $insert->execute();
    echo "Data accepted";
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() == 1062) {
        echo "Data rejected: this node already has an entry at that time";
    } else {
        echo "Data rejected: database constraint or insert error";
    }
}

$conn->close();
?>
