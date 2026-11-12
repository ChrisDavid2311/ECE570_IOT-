<?php
$host = "localhost";
$user = "u902368618_db_chrisdavid";
$pwd  = "Saleth@18";
$db   = "u902368618_christydavid";

$conn = new mysqli($host, $user, $pwd, $db);

if ($conn->connect_error) {
    die("Database connection failed");
}
$conn->set_charset("utf8mb4");

// Load registered node names for the dropdown.
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

if (count($nodeNames) === 0) {
    die("No registered sensor nodes found");
}

// Use the requested registered node, or the first registered node.
$requestedNode = $_GET["node_name"] ?? $nodeNames[0];
$selectedNode = in_array($requestedNode, $nodeNames, true)
    ? $requestedNode
    : $nodeNames[0];

// Load node registration and all sensor readings.
$register = $conn->query(
    "SELECT * FROM sensor_register ORDER BY node_name"
);

$data = $conn->query(
    "SELECT * FROM sensor_data ORDER BY node_name, time_received"
);

$activity = $conn->query(
    "SELECT r.node_name,
            COALESCE(a.activity_count, 0) AS valid_unique_count
     FROM sensor_register AS r
     LEFT JOIN sensor_activity AS a ON a.node_name = r.node_name
     ORDER BY r.node_name"
);

if (!$register || !$data || !$activity) {
    die("Could not load database tables");
}

// Calculate statistics for the selected node.
$statsQuery = $conn->prepare(
    "SELECT AVG(distance_cm) AS avg_distance,
            MIN(distance_cm) AS min_distance,
            MAX(distance_cm) AS max_distance,
            AVG(light_value) AS avg_light,
            MIN(light_value) AS min_light,
            MAX(light_value) AS max_light
     FROM sensor_data
     WHERE node_name = ?"
);

$statsQuery->bind_param("s", $selectedNode);
$statsQuery->execute();
$stats = $statsQuery->get_result()->fetch_assoc();

// Get the selected node's activity count.
$countQuery = $conn->prepare(
    "SELECT activity_count
     FROM sensor_activity
     WHERE node_name = ?"
);

$countQuery->bind_param("s", $selectedNode);
$countQuery->execute();
$countRow = $countQuery->get_result()->fetch_assoc();
$selectedCount = $countRow ? (int)$countRow["activity_count"] : 0;

// Get only this node's values for the chart.
$chartQuery = $conn->prepare(
    "SELECT time_received, distance_cm, light_value
     FROM sensor_data
     WHERE node_name = ?
     ORDER BY time_received"
);

$chartQuery->bind_param("s", $selectedNode);
$chartQuery->execute();
$chartResult = $chartQuery->get_result();

$chartTimes = [];
$chartDistances = [];
$chartLight = [];

while ($row = $chartResult->fetch_assoc()) {
    $chartTimes[] = $row["time_received"];
    $chartDistances[] = $row["distance_cm"] === null
        ? null
        : (float)$row["distance_cm"];
    $chartLight[] = $row["light_value"] === null
    ? null
    : adcToVoltage($row["light_value"]);
}

function showTable($result) {
    echo "<table><tr>";

    foreach ($result->fetch_fields() as $field) {
        echo "<th>" . htmlspecialchars($field->name, ENT_QUOTES, "UTF-8") . "</th>";
    }

    echo "</tr>";

    while ($row = $result->fetch_assoc()) {
        echo "<tr>";

        foreach ($row as $value) {
            $displayValue = $value === null ? "" : (string)$value;
            echo "<td>" .
                htmlspecialchars($displayValue, ENT_QUOTES, "UTF-8") .
                "</td>";
        }

        echo "</tr>";
    }

    echo "</table>";
}

function adcToVoltage($value) {
    if ($value === null) {
        return null;
    }

    return (float)$value * 3.3 / 1023.0;
}



function showStat($value, $unit = "") {
    if ($value === null) {
        return "No data";
    }

    return number_format((float)$value, 2) . $unit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="refresh" content="30">
    <title>ESP Sensor Dashboard</title>

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
            max-width: 950px;
            height: 430px;
            margin-bottom: 35px;
        }

        .stats p {
            margin: 8px 0;
        }
    </style>
</head>
<body>

<h1>ECE 570 ESP Sensor Dashboard</h1>

<p><a href="sensor_graph.php">Open temperature/humidity dashboard</a></p>
<p>This dashboard refreshes automatically every 30 seconds.</p>

<h2>Registered Sensor Nodes</h2>
<?php showTable($register); ?>

<h2>Valid Unique Measurement Counts</h2>
<?php showTable($activity); ?>

<form method="get">
    <label for="node_name">Choose a registered node:</label>

    <select name="node_name" id="node_name" onchange="this.form.submit()">
        <?php foreach ($nodeNames as $nodeName): ?>
            <option value="<?php echo htmlspecialchars($nodeName, ENT_QUOTES, "UTF-8"); ?>"
                <?php if ($nodeName === $selectedNode) echo "selected"; ?>>
                <?php echo htmlspecialchars($nodeName, ENT_QUOTES, "UTF-8"); ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

<h2>Statistics for <?php echo htmlspecialchars($selectedNode, ENT_QUOTES, "UTF-8"); ?></h2>

<div class="stats">
    <p>Valid unique measurement count:
        <?php echo $selectedCount; ?>
    </p>

    <p>Average distance:
        <?php echo showStat($stats["avg_distance"], " cm"); ?>
    </p>
    <p>Minimum distance:
        <?php echo showStat($stats["min_distance"], " cm"); ?>
    </p>
    <p>Maximum distance:
        <?php echo showStat($stats["max_distance"], " cm"); ?>
    </p>

    <p>Average LDR voltage:
    <?php echo showStat(adcToVoltage($stats["avg_light"]), " V"); ?>
    </p>
    <p>Minimum LDR voltage:
    <?php echo showStat(adcToVoltage($stats["min_light"]), " V"); ?>
    </p>
    <p>Maximum LDR voltage:
    <?php echo showStat(adcToVoltage($stats["max_light"]), " V"); ?>
    </p>
</div>

<h2>Readings for <?php echo htmlspecialchars($selectedNode, ENT_QUOTES, "UTF-8"); ?></h2>

<label for="chartType">Graph type:</label>
<select id="chartType">
    <option value="line">Line</option>
    <option value="bar">Bar</option>
</select>

<div class="chart-container">
    <canvas id="sensorChart"></canvas>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.1/chart.min.js"></script>

<script>
const chartTimes = <?php
    echo json_encode(
        $chartTimes,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    );
?>;

const chartDistances = <?php
    echo json_encode(
        $chartDistances,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    );
?>;

const chartLight = <?php
    echo json_encode(
        $chartLight,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    );
?>;

const selectedNode = <?php
    echo json_encode(
        $selectedNode,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    );
?>;

const chartCanvas = document.getElementById("sensorChart");
let sensorChart;

function drawChart(type) {
    if (sensorChart) {
        sensorChart.destroy();
    }

    sensorChart = new Chart(chartCanvas, {
        type: type,
        data: {
            labels: chartTimes,
            datasets: [
                {
                    label: "Distance (cm)",
                    data: chartDistances,
                    yAxisID: "distanceAxis",
                    borderColor: "#1769aa",
                    backgroundColor: "rgba(23, 105, 170, 0.35)",
                    spanGaps: false
                },
                {
                    label: "LDR voltage (V)",
                    data: chartLight,
                    yAxisID: "lightAxis",
                    borderColor: "#e08b16",
                    backgroundColor: "rgba(224, 139, 22, 0.35)",
                    spanGaps: false
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: "Sensor readings for " + selectedNode
                }
            },
            scales: {
                distanceAxis: {
                    type: "linear",
                    position: "left",
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: "Distance (cm)"
                    }
                },
                lightAxis: {
                    type: "linear",
                    position: "right",
                    beginAtZero: true,
                    grid: {
                        drawOnChartArea: false
                    },
                    title: {
                        display: true,
                        text: "LDR voltage (V)"
                    }
                }
            }
        }
    });
}

const chartTypeMenu = document.getElementById("chartType");
drawChart(chartTypeMenu.value);
chartTypeMenu.addEventListener("change", function () {
    drawChart(this.value);
});
</script>

</body>
</html>

<?php
$conn->close();
?>
