<?php
$host = "localhost";
$user = "u902368618_db_chrisdavid";
$pwd = "";
$db = "u902368618_christydavid";

$conn = new mysqli($host, $user, $pwd, $db);


if ($conn->connect_error) {
    die("Database connection failed");
}

// Get all registered nodes for the dropdown
$nodeResult = $conn->query(
    "SELECT node_name FROM sensor_register ORDER BY node_name"
);

if (!$nodeResult) {
    die("Could not load registered nodes");
}

$nodeNames = [];

while ($row = $nodeResult->fetch_assoc()) {
    $nodeNames[] = $row["node_name"];
}

if (count($nodeNames) == 0) {
    die("No registered sensor nodes found");
}

// Use the requested node, or node_5 at first
$selectedNode = $_GET["node_name"] ?? "node_5";

// If the requested node isn't registered, use the first node
if (!in_array($selectedNode, $nodeNames, true)) {
    $selectedNode = $nodeNames[0];
}

// Get all registered-node rows for the table
$register = $conn->query(
    "SELECT * FROM sensor_register ORDER BY node_name"
);

// Get all sensor readings, sorted by node and time
$data = $conn->query(
    "SELECT * FROM sensor_data ORDER BY node_name, time_received"
);

if (!$register || !$data) {
    die("Could not load table data");
}

// Calculate statistics for the selected node
$statsQuery = $conn->prepare(
    "SELECT AVG(temperature) AS avg_temperature,
            AVG(humidity) AS avg_humidity,
            MAX(temperature) AS max_temperature,
            MIN(humidity) AS min_humidity
     FROM sensor_data
     WHERE node_name = ?"
);

$statsQuery->bind_param("s", $selectedNode);
$statsQuery->execute();
$stats = $statsQuery->get_result()->fetch_assoc();

// Get this node's time and temperature values for the chart
$chartQuery = $conn->prepare(
    "SELECT time_received, temperature
     FROM sensor_data
     WHERE node_name = ?
     ORDER BY time_received"
);

$chartQuery->bind_param("s", $selectedNode);
$chartQuery->execute();
$chartResult = $chartQuery->get_result();

$chartTimes = [];
$chartTemperatures = [];

while ($row = $chartResult->fetch_assoc()) {
    $chartTimes[] = $row["time_received"];
    $chartTemperatures[] = (float)$row["temperature"];
}

// Display all columns and rows from a database result
function showTable($result)
{
    echo "<table>";
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

<!DOCTYPE html>
<html>
<head>
    <title>Sensor Graph and Statistics</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 30px;
        }

        table {
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        th, td {
            border: 1px solid #777;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #117a72;
            color: white;
        }

        td {
            background-color: #f5f5f5;
        }

        .chart-container {
            max-width: 900px;
        }
    </style>
</head>
<body>

<h1>ECE 570 Sensor Database</h1>

<h2>Registered Sensor Nodes</h2>
<?php showTable($register); ?>

<h2>Sensor Data Received</h2>
<?php showTable($data); ?>

<form method="get">
    <label for="node_name">Choose a sensor node:</label>

    <select name="node_name" id="node_name" onchange="this.form.submit()">
        <?php foreach ($nodeNames as $nodeName): ?>
            <option value="<?php echo htmlspecialchars($nodeName); ?>"
                <?php if ($nodeName === $selectedNode) echo "selected"; ?>>
                <?php echo htmlspecialchars($nodeName); ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

<h2>Statistics for <?php echo htmlspecialchars($selectedNode); ?></h2>

<p>Average Temperature:
    <?php
    echo $stats["avg_temperature"] === null
        ? "No data"
        : number_format((float)$stats["avg_temperature"], 2) . " °F";
    ?>
</p>

<p>Average Humidity:
    <?php
    echo $stats["avg_humidity"] === null
        ? "No data"
        : number_format((float)$stats["avg_humidity"], 2) . " %";
    ?>
</p>

<p>Maximum Temperature:
    <?php
    echo $stats["max_temperature"] === null
        ? "No data"
        : number_format((float)$stats["max_temperature"], 2) . " °F";
    ?>
</p>

<p>Minimum Humidity:
    <?php
    echo $stats["min_humidity"] === null
        ? "No data"
        : number_format((float)$stats["min_humidity"], 2) . " %";
    ?>
</p>

<h2>Sensor Node <?php echo htmlspecialchars($selectedNode); ?></h2>

<div class="chart-container">
    
   <label for="chartType">Graph type:</label>
<select id="chartType">
    <option value="bar">Bar</option>
    <option value="line">Line</option>
</select>

<canvas id="mycanvas"></canvas>


</div>

<script>
    const chartTimes = <?php
        echo json_encode(
            $chartTimes,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        );
    ?>;

    const chartTemperatures = <?php
        echo json_encode(
            $chartTemperatures,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        );
    ?>;

    const selectedNode = <?php
        echo json_encode(
            $selectedNode,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        );
    ?>;
</script>

<!-- Chart.js must load before app.js -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.1/chart.min.js"></script>
<script src="/chartjs/app.js?v=2"></script>

</body>
</html>

<?php
$conn->close();
?>
