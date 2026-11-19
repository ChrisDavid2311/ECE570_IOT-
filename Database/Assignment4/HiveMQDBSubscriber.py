import paho.mqtt.client as mqtt
import mysql.connector

# HiveMQ settings: keep the settings that already work for your subscriber.
BROKER_URL = "broker.hivemq.com"
BROKER_PORT = 8000
VOLTAGE_TOPIC = "testtopic/temp/outTopic/christy_esp8266_570/voltage"
LED_TOPIC = "testtopic/temp/inTopic"

# Replace these four placeholders with the values shown in Hostinger's
# Remote MySQL settings and database-management page.
DB_HOST = "srv562.hstgr.io"
DB_USER = "u902368618_db_chrisdavid"
DB_PASSWORD = "Saleth@18"
DB_NAME = "u902368618_christydavid"
DB_PORT = 3306

# This name is already registered in your sensor_register table.
NODE_NAME = "node_5"


def open_database():
    return mysql.connector.connect(
        host=DB_HOST,
        user=DB_USER,
        password=DB_PASSWORD,
        database=DB_NAME,
        port=DB_PORT,
        connection_timeout=10
    )


def save_voltage(value):
    connection = None
    cursor = None

    try:
        connection = open_database()
        cursor = connection.cursor()
        cursor.execute(
            """
            INSERT INTO sensor_data
                (node_name, time_received, potentiometer_voltage)
            VALUES
                (%s, CURRENT_TIMESTAMP, %s)
            """,
            (NODE_NAME, value)
        )
        connection.commit()
        print(f"Saved voltage {value:.3f} V to sensor_data.")

    except mysql.connector.Error as error:
        print(f"Voltage database insert failed: {error}")

    finally:
        if cursor is not None:
            cursor.close()
        if connection is not None and connection.is_connected():
            connection.close()


def save_led_command(topic, state):
    connection = None
    cursor = None

    try:
        connection = open_database()
        cursor = connection.cursor()
        cursor.execute(
            """
            INSERT INTO led_commands (topic, led_state)
            VALUES (%s, %s)
            """,
            (topic, int(state))
        )
        connection.commit()
        print(f"Saved LED command {state} to led_commands.")

    except mysql.connector.Error as error:
        print(f"LED command database insert failed: {error}")

    finally:
        if cursor is not None:
            cursor.close()
        if connection is not None and connection.is_connected():
            connection.close()


def on_connect(client, userdata, flags, rc):
    if rc == 0:
        print("Connected to HiveMQ broker.")
        client.subscribe(VOLTAGE_TOPIC)
        client.subscribe(LED_TOPIC)
        print(f"Subscribed to voltage topic: {VOLTAGE_TOPIC}")
        print(f"Subscribed to LED command topic: {LED_TOPIC}")
    else:
        print(f"MQTT connection failed with code {rc}.")


def on_message(client, userdata, msg):
    payload = msg.payload.decode("utf-8").strip()

    if msg.topic == VOLTAGE_TOPIC:
        numeric_text = payload.removesuffix(" V").strip()

        try:
            value = float(numeric_text)
        except ValueError:
            print(f"Ignoring nonnumeric voltage message: {payload!r}")
            return

        print(f"Received {value:.3f} V from {msg.topic}")
        save_voltage(value)

    elif msg.topic == LED_TOPIC:
        if payload not in ("0", "1"):
            print(f"Ignoring unexpected LED command: {payload!r}")
            return

        print(f"Received LED command {payload} from {msg.topic}")
        save_led_command(msg.topic, payload)


client = mqtt.Client(transport="websockets")
client.on_connect = on_connect
client.on_message = on_message

print("Connecting to HiveMQ...")
client.connect(BROKER_URL, BROKER_PORT, 60)
client.loop_forever()