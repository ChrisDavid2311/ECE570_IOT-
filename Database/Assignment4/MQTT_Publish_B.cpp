#include <Arduino.h>
#include <ESP8266WiFi.h>
#include <WiFiClientSecure.h>
#include <PubSubClient.h>

// Replace these with your Wi-Fi network name and password.
const char* ssid = "ChrisDavidiPhone17";
const char* password = "Jeeva@18";

// HiveMQ public broker, MQTT over TLS.
const char* mqtt_server = "broker.hivemq.com";
const int mqtt_port = 8883;

// Change this to a topic that is unique to you.
// Set the Python subscriber to this exact same topic.
const char* publishTopic =
    "testtopic/temp/outTopic/christy_esp8266_570";

const int POT_PIN = A0;
const int BUTTON_PIN = D5;
const int LED_PIN = D6;

const unsigned long publishInterval = 15000;
unsigned long lastPublish = 0;

WiFiClientSecure secureClient;
PubSubClient mqttClient(secureClient);

void connectToWiFi() {
  Serial.print("Connecting to Wi-Fi: ");
  Serial.println(ssid);

  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid, password);

  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }

  Serial.println();
  Serial.println("Wi-Fi connected.");
  Serial.print("ESP IP address: ");
  Serial.println(WiFi.localIP());
}

void reconnectToMQTT() {
  while (!mqttClient.connected()) {
    Serial.print("Connecting to MQTT broker...");

    String clientId = "ESP8266Client-";
    clientId += String(random(0xffff), HEX);

    if (mqttClient.connect(clientId.c_str())) {
      Serial.println(" connected.");
    } else {
      Serial.print(" failed, MQTT state = ");
      Serial.print(mqttClient.state());
      Serial.println(". Retrying in 5 seconds.");
      delay(5000);
    }
  }
}

void setup() {
  Serial.begin(115200);

  pinMode(BUTTON_PIN, INPUT_PULLUP);
  pinMode(LED_PIN, OUTPUT);
  digitalWrite(LED_PIN, LOW);

  connectToWiFi();

  // Demo setting: encrypts the connection but does not verify the broker's
  // certificate. Use only for this public-broker class exercise.
  secureClient.setInsecure();

  mqttClient.setServer(mqtt_server, mqtt_port);

  randomSeed(micros());

  Serial.println("Enter 1 to turn the LED on, or 0 to turn it off.");
  Serial.print("Publishing to: ");
  Serial.println(publishTopic);
}

void loop() {
  if (WiFi.status() != WL_CONNECTED) {
    connectToWiFi();
  }

  if (!mqttClient.connected()) {
    reconnectToMQTT();
  }

  mqttClient.loop();

  // Preserve the serial command for local LED control.
  if (Serial.available() > 0) {
    char command = Serial.read();

    if (command == '1') {
      digitalWrite(LED_PIN, HIGH);
      Serial.println("LED ON");
    } else if (command == '0') {
      digitalWrite(LED_PIN, LOW);
      Serial.println("LED OFF");
    }
  }

  // Publish once every 15 seconds.
  unsigned long now = millis();
  if (now - lastPublish >= publishInterval) {
    lastPublish = now;

    int rawValue = analogRead(POT_PIN);
    rawValue = constrain(rawValue, 0, 1024);

    // Calibrated approximately to your 3.290 V multimeter reading at full scale.
    float voltage = rawValue * (3.290 / 1024.0);

    int buttonPressed = (digitalRead(BUTTON_PIN) == LOW) ? 1 : 0;

    char payload[32];
    snprintf(payload, sizeof(payload), "%.3f V", voltage);

    Serial.print("Potentiometer ADC value: ");
    Serial.println(rawValue);
    Serial.print("Potentiometer voltage: ");
    Serial.print(voltage, 3);
    Serial.println(" V");
    Serial.print("Switch: ");
    Serial.println(buttonPressed);

    if (mqttClient.publish(publishTopic, payload)) {
      Serial.print("Published ");
      Serial.print(payload);
      Serial.print(" to ");
      Serial.println(publishTopic);
    } else {
      Serial.println("Publish failed.");
    }
  }
}
