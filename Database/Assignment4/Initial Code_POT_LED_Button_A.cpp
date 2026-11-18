#include <Arduino.h>

const int POT_PIN = A0;
const int BUTTON_PIN = D5;
const int LED_PIN = D6;

void setup() {
  Serial.begin(115200);

  pinMode(BUTTON_PIN, INPUT_PULLUP);
  pinMode(LED_PIN, OUTPUT);
  digitalWrite(LED_PIN, LOW);

  Serial.println("Enter 1 to turn the LED on, or 0 to turn it off.");
}

void loop() {
  int potValue = analogRead(POT_PIN);

  // NodeMCU V2 A0 range is approximately 0–3.3 V.
  float voltage = potValue * (3.3 / 1023.0);

  Serial.print("Potentiometer ADC value: ");
  Serial.println(potValue);

  Serial.print("Potentiometer voltage: ");
  Serial.print(voltage, 3);
  Serial.println(" V");

  // With INPUT_PULLUP, a pressed button reads LOW.
  int buttonPressed = (digitalRead(BUTTON_PIN) == LOW) ? 1 : 0;
  Serial.print("Switch: ");
  Serial.println(buttonPressed);

  // Type 1 or 0 in the Serial Monitor and press Send.
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

  delay(1000);
}
