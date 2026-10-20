CREATE TABLE sensor_register (
    node_name VARCHAR(10) PRIMARY KEY,
    manufacturer VARCHAR(10) NOT NULL,
    longitude DECIMAL(15,10),
    latitude DECIMAL(15,10)
);

CREATE TABLE sensor_data (
    node_name VARCHAR(10) NOT NULL,
    time_received DATETIME NOT NULL,
    temperature DECIMAL(6,2) NOT NULL,
    humidity DECIMAL(6,2) NOT NULL,

    PRIMARY KEY (node_name, time_received),

    FOREIGN KEY (node_name)
        REFERENCES sensor_register(node_name),

    CHECK (temperature BETWEEN -10 AND 100),
    CHECK (humidity BETWEEN 0 AND 100)
);


INSERT INTO sensor_register
(node_name, manufacturer, longitude, latitude)
VALUES
('node_1', 'Bosch', -122.7044, 38.3396),
('node_2', 'Siemens', -122.7050, 38.3400),
('node_3', 'Honeywell', -122.7060, 38.3410),
('node_4', 'Omron', -122.7070, 38.3420),
('node_5', 'Sensirion', -122.7080, 38.3430);

SELECT * FROM sensor_register;


INSERT INTO sensor_data
(node_name, time_received, temperature, humidity)
VALUES
('node_1', '2022-10-01 11:00:00', 25.50, 60.00),
('node_1', '2022-10-01 11:30:00', 26.00, 61.00),
('node_1', '2022-10-01 12:00:00', 26.50, 62.00),
('node_1', '2022-10-01 12:30:00', 27.00, 63.00),

('node_2', '2022-10-01 11:00:00', 22.00, 55.00),
('node_2', '2022-10-01 11:30:00', 22.50, 56.00),
('node_2', '2022-10-01 12:00:00', 23.00, 57.00),
('node_2', '2022-10-01 12:30:00', 23.50, 58.00),

('node_3', '2022-10-01 11:00:00', 28.00, 65.00),
('node_3', '2022-10-01 11:30:00', 28.50, 66.00),
('node_3', '2022-10-01 12:00:00', 29.00, 67.00),
('node_3', '2022-10-01 12:30:00', 29.50, 68.00),

('node_4', '2022-10-01 11:00:00', 20.00, 50.00),
('node_4', '2022-10-01 11:30:00', 20.50, 51.00),
('node_4', '2022-10-01 12:00:00', 21.00, 52.00),
('node_4', '2022-10-01 12:30:00', 21.50, 53.00),

('node_5', '2022-10-01 11:00:00', 24.00, 58.00),
('node_5', '2022-10-01 11:30:00', 24.50, 59.00),
('node_5', '2022-10-01 12:00:00', 25.00, 60.00),
('node_5', '2022-10-01 12:30:00', 25.50, 61.00);


INSERT INTO sensor_data
(node_name, time_received, temperature, humidity)
VALUES
('node_6', '2022-10-01 11:00:00', 25.00, 60.00);


CREATE VIEW sensor_combined AS
SELECT
    sensor_data.node_name,
    sensor_register.manufacturer,
    sensor_register.longitude,
    sensor_register.latitude,
    sensor_data.time_received,
    sensor_data.temperature,
    sensor_data.humidity
FROM sensor_data
INNER JOIN sensor_register
ON sensor_data.node_name = sensor_register.node_name;


SELECT * FROM sensor_combined
ORDER BY node_name, time_received;


SHOW TABLES;

DESCRIBE sensor_register;

DESCRIBE sensor_data;

SELECT node_name, COUNT(*) AS total_readings
FROM sensor_data
GROUP BY node_name;
