<?php
$host = "localhost";
$user = "u902368618_db_chrisdavid";
$pwd  = "";
$db   = "u902368618_christydavid";



$conn = new mysqli($host, $user, $pwd, $db);

if ($conn->connect_error) {
    die("Database connection failed");
}

$conn->query("SET time_zone = '-07:00'");

$node = $_GET["node_name"] ?? "";
$temp = $_GET["temperature"] ?? "";
$humidity = $_GET["humidity"] ?? "";
$time = $_GET["timeReceived"] ?? "";

// Check required URL values
if ($node === "" || $temp === "" || $humidity === "") {
    die("Missing nodeId, nodeTemp, or nodeHumidity");
}

// Check that the node is registered
$check = $conn->prepare(
    "SELECT node_name FROM sensor_register WHERE node_name = ?"
);
$check->bind_param("s", $node);
$check->execute();
$check->store_result();

if ($check->num_rows == 0) {
    die("Data rejected: node is not registered");
}


// If a time is provided, use it.
// Otherwise, let the database assign the current time.
if ($time === "") {
    $insert = $conn->prepare(
        "INSERT INTO sensor_data (node_name, temperature, humidity)
         VALUES (?, ?, ?)"
    );
    $insert->bind_param("sdd", $node, $temp, $humidity);
} else {
    $date = DateTime::createFromFormat("m-d-Y H:i:s", $time);

    if (!$date || $date->format("m-d-Y H:i:s") !== $time) {
        die("Data rejected: use time format MM-DD-YYYY HH:MM:SS");
    }

    $sqlTime = $date->format("Y-m-d H:i:s");

    $insert = $conn->prepare(
        "INSERT INTO sensor_data
         (node_name, time_received, temperature, humidity)
         VALUES (?, ?, ?, ?)"
    );
    $insert->bind_param("ssdd", $node, $sqlTime, $temp, $humidity);
}

if ($insert->execute()) {
    echo "Data accepted";
} else {
    if ($conn->errno == 1062) {
        echo "Data rejected: this node already has an entry at that time";
    } else {
        echo "Data rejected: database insert failed";
    }
}

$conn->close();
?>
