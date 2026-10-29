<?php
header("Content-Type: application/json; charset=utf-8");

$host = "localhost";
$user = "u902368618_db_chrisdavid";
$pwd = "";
$db = "u902368618_christydavid";

$conn = new mysqli($host, $user, $pwd, $db);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

// Get the node name from the URL
$nodeName = $_GET["node_name"] ?? "";

if ($nodeName === "") {
    http_response_code(400);
    echo json_encode(["error" => "Please provide node_name"]);
    exit;
}

// Get this node's readings, sorted by time
$query = $conn->prepare(
    "SELECT node_name, time_received, temperature, humidity
     FROM sensor_data
     WHERE node_name = ?
     ORDER BY time_received"
);

$query->bind_param("s", $nodeName);
$query->execute();
$result = $query->get_result();

$readings = [];

while ($row = $result->fetch_assoc()) {
    $readings[] = [
        "node_name" => $row["node_name"],
        "time_received" => $row["time_received"],
        "temperature" => (float)$row["temperature"],
        "humidity" => (float)$row["humidity"]
    ];
}

echo json_encode($readings, JSON_PRETTY_PRINT);

$conn->close();
?>
