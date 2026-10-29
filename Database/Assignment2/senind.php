<?php
$host = "localhost";
$user = "u902368618_db_chrisdavid";
$pwd = "";
$db = "u902368618_christydavid";

$conn = new mysqli($host, $user, $pwd, $db);


if ($conn->connect_error) {
    die("Database connection failed");
}

$register = $conn->query("SELECT * FROM sensor_register ORDER BY node_name");
$data = $conn->query("SELECT * FROM sensor_data ORDER BY node_name, time_received");

$average = $conn->query(
    "SELECT AVG(temperature) AS avg_temperature,
            AVG(humidity) AS avg_humidity
     FROM sensor_data
     WHERE node_name = 'node_5'"
);

if (!$register || !$data || !$average) {
    die("Database query failed");
}

if (!$register || !$data) {
    die("Database query failed");
}

function showTable($result) {
    echo "<table border='1' cellpadding='8' cellspacing='0'>";

    echo "<tr>";
    foreach ($result->fetch_fields() as $field) {
        echo "<th>" . htmlspecialchars($field->name) . "</th>";
    }
    echo "</tr>";

    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars((string)$value) . "</td>";
        }
        echo "</tr>";
    }

    echo "</table>";
}
?>

<html>
<head>
    <title>ECE 570 IoT Sensor Data</title>
    <style>
        body { font-family: Arial; margin: 30px; }
        table { border-collapse: collapse; margin-bottom: 30px; }
        th { background: #117a72; color: white; }
        td { background: #f5f5f5; }
    </style>
</head>
<body>

<h1>ECE 570 Sensor Database</h1>

<h2>Registered Sensor Nodes</h2>
<?php showTable($register); ?>

<h2>Sensor Data Received</h2>
<?php showTable($data); ?>

<?php
$row = $average->fetch_assoc();

echo "<p>The average temperature for node 5 is: "
    . round($row["avg_temperature"], 2) . " °F</p>";

echo "<p>The average humidity for node 5 is: "
    . round($row["avg_humidity"], 2) . " %</p>";
?>

</body>
</html>

