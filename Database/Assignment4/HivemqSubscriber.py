
# import paho.mqtt as mqtt
import paho.mqtt.client as mqtt

# Define the MQTT broker and topic
BROKER_URL = "broker.hivemq.com"  # Public HiveMQ broker
BROKER_PORT = 8000               # WebSocket port
TOPIC = "testtopic/temp/outTopic/christy_esp8266_570"   # Topic to subscribe to
TOPIC = "testtopic/temp/outTopic/christy_esp8266_570/voltage"   # Topic to subscribe to

# Define callback for connection
def on_connect(client, userdata, flags, rc):
    if rc == 0:
        print("Connected to HiveMQ broker!")
        # Subscribe to the topic once connected
        client.subscribe(TOPIC)
        print(f"Subscribed to topic: {TOPIC}")
    else:
        print(f"Failed to connect, return code {rc}")

# Define callback for receiving messages
def on_message(client, userdata, msg):
    print(f"Received message: {msg.payload.decode()} from topic: {msg.topic}")

# Create an MQTT client instance
client = mqtt.Client(transport="websockets")

# Attach callback functions
client.on_connect = on_connect
client.on_message = on_message

# Connect to the HiveMQ broker
print("Connecting to broker...")
client.connect(BROKER_URL, BROKER_PORT, 60)

# Start the MQTT client loop
client.loop_forever()
