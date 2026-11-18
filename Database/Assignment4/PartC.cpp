#include <Arduino.h>
#include <ESP8266WiFi.h>
#include <WiFiClientSecure.h>
#include <PubSubClient.h>

// Replace with your Wi-Fi network name and password.
const char* ssid = "";
const char* password = "";

// HiveMQ public broker using MQTT over TLS.
const char* mqttServer = "broker.hivemq.com";
const int mqttPort = 8883;

// ESP subscribes here for LED commands: send payload 1 or 0.
const char* inputTopic = "testtopic/temp/inTopic";

// ESP publishes switch states here.
const char* switchTopic =
    "testtopic/temp/outTopic/christy_esp8266_570";

// Keep the potentiometer data on its own topic.
const char* voltageTopic =
    "testtopic/temp/outTopic/christy_esp8266_570/voltage";

const int POT_PIN = A0;
const int BUTTON_PIN = D5;
const int LED_PIN = D6;

const unsigned long publishInterval = 15000;
const unsigned long debounceInterval = 30;
const unsigned long switchPulseDuration = 5000;

unsigned long lastVoltagePublish = 0;
unsigned long lastButtonChange = 0;
unsigned long switchZeroDue = 0;

bool lastRawButtonPressed = false;
bool stableButtonPressed = false;
bool sendSwitchZero = false;

WiFiClientSecure secureClient;
PubSubClient mqttClient(secureClient);

void connectToWiFi() {
  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid, password);

  Serial.print("Connecting to Wi-Fi");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }

  Serial.println();
  Serial.println("Wi-Fi connected.");
  Serial.print("IP address: ");
  Serial.println(WiFi.localIP());
}

void mqttCallback(char* topic, byte* payload, unsigned int length) {
  // Only accept a single-character 1 or 0 on the input topic.
  if (strcmp(topic, inputTopic) != 0 || length != 1) {
    return;
  }

  if (payload[0] == '1') {
    digitalWrite(LED_PIN, HIGH);
    Serial.println("Received 1: LED ON");
  } else if (payload[0] == '0') {
    digitalWrite(LED_PIN, LOW);
    Serial.println("Received 0: LED OFF");
  }
}

void reconnectToMQTT() {
  while (!mqttClient.connected()) {
    Serial.print("Connecting to HiveMQ...");

    String clientId = "ESP8266Client-";
    clientId += String(random(0xffff), HEX);

    if (mqttClient.connect(clientId.c_str())) {
      Serial.println(" connected.");

      if (mqttClient.subscribe(inputTopic)) {
        Serial.print("Subscribed to ");
        Serial.println(inputTopic);
      } else {
        Serial.println("Subscription failed.");
      }
    } else {
      Serial.print(" failed, MQTT state = ");
      Serial.print(mqttClient.state());
      Serial.println(". Retrying in 5 seconds.");
      delay(5000);
    }
  }
}

void publishSwitchState(const char* state) {
  if (mqttClient.publish(switchTopic, state)) {
    Serial.print("Published switch state ");
    Serial.print(state);
    Serial.print(" to ");
    Serial.println(switchTopic);
  } else {
    Serial.println("Switch-state publish failed.");
  }
}

void setup() {
  Serial.begin(115200);

  pinMode(BUTTON_PIN, INPUT_PULLUP);
  pinMode(LED_PIN, OUTPUT);
  digitalWrite(LED_PIN, LOW);

  connectToWiFi();

  // Demo setting for this public-broker assignment.
  // It encrypts traffic but skips server-certificate verification.
  secureClient.setInsecure();

  mqttClient.setServer(mqttServer, mqttPort);
  mqttClient.setCallback(mqttCallback);

  randomSeed(micros());

  Serial.println("Ready.");
}

void loop() {
  if (WiFi.status() != WL_CONNECTED) {
    connectToWiFi();
  }

  if (!mqttClient.connected()) {
    reconnectToMQTT();
  }

  // Keep this running often so incoming LED commands are handled promptly.
  mqttClient.loop();

  unsigned long now = millis();

  // Debounce the pushbutton and detect a new press.
  bool rawButtonPressed = (digitalRead(BUTTON_PIN) == LOW);

  if (rawButtonPressed != lastRawButtonPressed) {
    lastRawButtonPressed = rawButtonPressed;
    lastButtonChange = now;
  }

  if ((now - lastButtonChange >= debounceInterval) &&
      (rawButtonPressed != stableButtonPressed)) {
    stableButtonPressed = rawButtonPressed;

    if (stableButtonPressed) {
      // On press, publish 1 and schedule 0 five seconds later.
      publishSwitchState("1");
      switchZeroDue = now + switchPulseDuration;
      sendSwitchZero = true;
    }
  }

  // Publish 0 five seconds after the press.
  if (sendSwitchZero && (long)(now - switchZeroDue) >= 0) {
    publishSwitchState("0");
    sendSwitchZero = false;
  }

  // Publish the potentiometer voltage every 15 seconds.
  if (now - lastVoltagePublish >= publishInterval) {
    lastVoltagePublish = now;

    int rawValue = constrain(analogRead(POT_PIN), 0, 1024);
    float voltage = rawValue * (3.290 / 1024.0);

    char payload[32];
    snprintf(payload, sizeof(payload), "%.3f V", voltage);

    Serial.print("Potentiometer voltage: ");
    Serial.println(payload);

    if (mqttClient.publish(voltageTopic, payload)) {
      Serial.print("Published voltage to ");
      Serial.println(voltageTopic);
    } else {
      Serial.println("Voltage publish failed.");
    }
  }
}
