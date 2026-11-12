#include <Arduino.h>
#include <Arduino.h>
#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <WiFiClientSecure.h>
#include <ArduinoJson.h>

const char* WIFI_SSID = "ChrisDavidiPhone17";
const char* WIFI_PASSWORD = "Jeeva@18";

const int BUTTON_PIN = D1;
const int TILT_PIN = D5;
const int TRIG_PIN = D6;
const int ECHO_PIN = D7;
const int LDR_PIN = A0;

const unsigned long DEBOUNCE_MS = 60;
const unsigned long RETRY_MS = 30000;
const unsigned long WIFI_RECONNECT_MS = 10000;

const char* SERVER_URL =
    "https://chrisdavid2311.com/esp_receive.php";

const int TILT_ACTIVE = HIGH;  // Change to LOW if your tilt activation reads 0

struct PendingRequest {
  String url;
  unsigned long lastAttempt;
  bool attempted;
};

const int MAX_PENDING = 6;
PendingRequest pending[MAX_PENDING];
int queueHead = 0;
int queueCount = 0;

int stableButton = HIGH;
int stableTilt = LOW;

void connectWiFi() {
    WiFi.mode(WIFI_STA);
    
    /*Serial.println("Scanning for Wi-Fi networks...");
int networkCount = WiFi.scanNetworks();
bool hotspotFound = false;

for (int i = 0; i < networkCount; i++) {
    String foundSSID = WiFi.SSID(i);

    Serial.print(i + 1);
    Serial.print(": ");
    Serial.print(foundSSID);
    Serial.print("  RSSI=");
    Serial.print(WiFi.RSSI(i));
    Serial.println(" dBm");

    if (foundSSID == String(WIFI_SSID)) {
        hotspotFound = true;
    }
}

if (hotspotFound) {
    Serial.println("Configured iPhone hotspot FOUND.");
} else {
    Serial.println("Configured iPhone hotspot NOT FOUND.");
}

WiFi.scanDelete();*/

       
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

    Serial.print("Connecting to WiFi");

    unsigned long start = millis();

    while (WiFi.status() != WL_CONNECTED &&
           millis() - start < 15000) {
        delay(500);
        Serial.print(".");
    }

    Serial.println();

    if (WiFi.status() == WL_CONNECTED) {
        Serial.println("WiFi connected!");

        Serial.print("IP Address: ");
        Serial.println(WiFi.localIP());

        Serial.print("MAC Address: ");
        Serial.println(WiFi.macAddress());

        Serial.print("RSSI: ");
        Serial.print(WiFi.RSSI());
        Serial.println(" dBm");
    } else {
        Serial.println("WiFi connection failed");
    }
}

void maintainWiFi() {
    static unsigned long lastCheck = 0;

    if (WiFi.status() == WL_CONNECTED) return;

    if (millis() - lastCheck >= WIFI_RECONNECT_MS) {
        lastCheck = millis();
        Serial.println("Reconnecting WiFi...");
        WiFi.reconnect();
    }
}

struct ZoneOption {
  const char* label;
  const char* ianaName;
};

const ZoneOption zones[] = {
  {"ET - New York",        "America/New_York"},
  {"CT - Chicago",         "America/Chicago"},
  {"MT - Denver",          "America/Denver"},
  {"PT - Los Angeles",     "America/Los_Angeles"},
  {"AKT - Anchorage",      "America/Anchorage"},
  {"HAT - Honolulu",       "Pacific/Honolulu"},
  {"AT - San Juan",        "America/Puerto_Rico"}
};

int selectedZone = 3;  // Option 4: Default Pacific Standard Time

int chooseTimeZone() {
  Serial.println();
  Serial.println("Select Your Time Zone (default is PST):");
  Serial.println("1) Eastern - New York");
  Serial.println("2) Central - Chicago");
  Serial.println("3) Mountain - Denver");
  Serial.println("4) Pacific - Los Angeles");
  Serial.println("5) Alaska - Anchorage");
  Serial.println("6) Hawaii-Aleutian - Honolulu");
  Serial.println("7) Atlantic - San Juan");
  Serial.println("Enter 1-7, then press Enter. Waiting 15 seconds...");

  String input = "";
  unsigned long start = millis();

  while (millis() - start < 15000) {
    while (Serial.available()) {
      char c = Serial.read();

      if (c == '\n' || c == '\r') {
        if (input.length() > 0) {
          int choice = input.toInt();
          if (choice >= 1 && choice <= 7) {
            return choice - 1;
          }
          Serial.println("Invalid choice; using PT.");
          return 3;
        }
      } else if (isDigit(c)) {
        input += c;
      }
    }
    delay(10);
  }

  if (input.length() > 0) {
    int choice = input.toInt();
    if (choice >= 1 && choice <= 7) {
      return choice - 1;
    }
  }

  Serial.println("No selection; using PT.");
  return 3;
}

String urlEncode(const String& value) {
  String encoded = "";

  for (unsigned int i = 0; i < value.length(); i++) {
    char c = value[i];

    if (isalnum((unsigned char)c) || c == '-' || c == '_' ||
        c == '.' || c == '~') {
      encoded += c;
    } else {
      char hex[4];
      snprintf(hex, sizeof(hex), "%%%02X", (unsigned char)c);
      encoded += hex;
    }
  }

  return encoded;
}

bool getTimestamp(String& timestamp) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("No Wi-Fi; timestamp unavailable.");
    return false;
  }

  WiFiClientSecure client;
  client.setInsecure();  // Classroom prototype: skips certificate verification

  HTTPClient http;
  String apiUrl =
      "https://timeapi.io/api/Time/current/zone?timeZone=";
  apiUrl += zones[selectedZone].ianaName;

  if (!http.begin(client, apiUrl)) {
    Serial.println("Could not start TimeAPI request.");
    return false;
  }

  http.setTimeout(12000);
  int status = http.GET();
  String response = http.getString();
  http.end();

  if (status != 200) {
    Serial.print("TimeAPI HTTP error: ");
    Serial.println(status);
    return false;
  }

  JsonDocument doc;
  DeserializationError error = deserializeJson(doc, response);

  if (error || !doc["dateTime"].is<const char*>()) {
    Serial.println("Could not read dateTime from TimeAPI.");
    return false;
  }

  timestamp = doc["dateTime"].as<String>();
  return timestamp.length() > 0;
}

float readDistanceCm() {
  digitalWrite(TRIG_PIN, LOW);
  delayMicroseconds(2);
  digitalWrite(TRIG_PIN, HIGH);
  delayMicroseconds(10);
  digitalWrite(TRIG_PIN, LOW);

  unsigned long duration = pulseIn(ECHO_PIN, HIGH, 30000);

  if (duration == 0) {
    return -1.0;
  }

  return duration * 0.0343 / 2.0;
}

void sendOldestPending() {
  if (queueCount == 0) return;

  PendingRequest& request = pending[queueHead];
  request.attempted = true;
  request.lastAttempt = millis();

  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("Wi-Fi disconnected; will retry this same request.");
    return;
  }

  WiFiClientSecure client;
  client.setInsecure();  // Classroom prototype: skips certificate verification

  HTTPClient http;
  if (!http.begin(client, request.url)) {
    Serial.println("Could not start server request; will retry.");
    return;
  }

  http.setTimeout(12000);
  int status = http.GET();
  String response = http.getString();
  response.trim();
  http.end();

  Serial.print("Server HTTP status: ");
  Serial.println(status);
  Serial.print("Server response: ");
  Serial.println(response);

  bool stored = (status == 201 && response == "stored");
  bool alreadyStored = (status == 200 && response == "already_stored");

  if (stored || alreadyStored) {
    Serial.println("Delivery confirmed.");

    request.url = "";
    queueHead = (queueHead + 1) % MAX_PENDING;
    queueCount--;

    // If another record is queued, attempt it on the next loop pass.
  } else {
    Serial.println("Not confirmed; same record will retry in 30 seconds.");
  }
}

void serviceRetries() {
  if (queueCount == 0) return;

  PendingRequest& request = pending[queueHead];

  if (!request.attempted ||
      millis() - request.lastAttempt >= RETRY_MS) {
    sendOldestPending();
  }
}

void recordMeasurement(const char* nodeName) {
  float distance = readDistanceCm();
  int light = analogRead(LDR_PIN);

  if (distance < 0) {
    Serial.println("No HC-SR04 echo; measurement not sent.");
    return;
  }

  String timestamp;
  if (!getTimestamp(timestamp)) {
    Serial.println("No timestamp; measurement not sent.");
    return;
  }

  if (queueCount >= MAX_PENDING) {
    Serial.println("Retry queue is full; measurement not queued.");
    return;
  }

  String url = String(SERVER_URL);
  url += "?node_name=";
  url += nodeName;
  url += "&time_received=";
  url += urlEncode(timestamp);
  url += "&distance_cm=";
  url += String(distance, 1);
  url += "&light_value=";
  url += String(light);

  int tail = (queueHead + queueCount) % MAX_PENDING;
  pending[tail].url = url;
  pending[tail].attempted = false;
  pending[tail].lastAttempt = 0;
  queueCount++;

  Serial.println();
  Serial.print("Queued ");
  Serial.print(nodeName);
  Serial.print(" at ");
  Serial.println(timestamp);
  Serial.print("Distance: ");
  Serial.print(distance, 1);
  Serial.println(" cm");
  Serial.print("Light value: ");
  Serial.println(light);

  // Try immediately. If it fails, retry the unchanged URL every 30 seconds.
  if (queueCount == 1) {
    sendOldestPending();
  }
}



void setup() {
  Serial.begin(115200);

  selectedZone = chooseTimeZone();

  Serial.print("Selected zone: ");
  Serial.println(zones[selectedZone].ianaName);

  pinMode(BUTTON_PIN, INPUT_PULLUP);
  pinMode(TILT_PIN, INPUT);
  pinMode(TRIG_PIN, OUTPUT);
  pinMode(ECHO_PIN, INPUT);

  stableButton = digitalRead(BUTTON_PIN);
  stableTilt = digitalRead(TILT_PIN);

  digitalWrite(TRIG_PIN, LOW);
  Serial.println();
  Serial.println("ECE570 sensor test ready");

  connectWiFi();

}

void loop() {
  maintainWiFi();
  serviceRetries();

  int buttonReading = digitalRead(BUTTON_PIN);

  // Button is wired to D1 and GND, so LOW means pressed.
  if (buttonReading != stableButton) {
    delay(DEBOUNCE_MS);
    buttonReading = digitalRead(BUTTON_PIN);

    if (buttonReading != stableButton) {
      stableButton = buttonReading;

      // Trigger only on the new press, not while it remains held.
      if (stableButton == LOW) {
        recordMeasurement("node_1");
      }
    }
  }

  int tiltReading = digitalRead(TILT_PIN);

  if (tiltReading != stableTilt) {
    delay(DEBOUNCE_MS);
    tiltReading = digitalRead(TILT_PIN);

    if (tiltReading != stableTilt) {
      stableTilt = tiltReading;

      // Trigger only when the tilt switch enters its active state.
      if (stableTilt == TILT_ACTIVE) {
        recordMeasurement("node_2");
      }
    }
  }

  delay(10);
}
