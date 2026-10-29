const typeMenu = document.getElementById("chartType");
const canvas = document.getElementById("mycanvas");

if (!typeMenu || !canvas) {
    console.error("Could not find chartType dropdown or mycanvas element.");
} else {
    let sensorChart;

    function drawChart() {
        if (sensorChart) {
            sensorChart.destroy();
        }

        const chosenType = typeMenu.value;

        sensorChart = new Chart(canvas, {
            type: chosenType,
            data: {
                labels: chartTimes,
                datasets: [{
                    label: "Temperature",
                    data: chartTemperatures,
                    backgroundColor: "rgba(128, 128, 128, 0.65)",
                    borderColor: "green",
                    borderWidth: 2,
                    fill: false,
                    tension: 0.2
                }]
            },
            options: {
                plugins: {
                    title: {
                        display: true,
                        text: "Sensor Node " + selectedNode
                    }
                },
                scales: {
                    x: {
                        title: { display: true, text: "Time" }
                    },
                    y: {
                        title: { display: true, text: "Temperature" }
                    }
                }
            }
        });
    }

    drawChart();
    typeMenu.addEventListener("change", drawChart);
}
