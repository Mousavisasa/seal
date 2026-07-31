#include <BLEDevice.h>
#include <BLEUtils.h>
#include <BLEServer.h>

#define SERVICE_UUID        "4fafc201-1fb5-459e-8fcc-c5c9c331914b"
#define CHARACTERISTIC_UUID "beb5483e-36e1-4688-b7f5-ea07361b26a8"

#ifndef LED_BUILTIN
#define LED_BUILTIN 48   // اگر بردت LED داخلی دیگری دارد، این را عوض کن
#endif

BLECharacteristic *pCharacteristic;
bool ledState = false;

class MyCharacteristicCallbacks : public BLECharacteristicCallbacks {
  void onWrite(BLECharacteristic *pCharacteristic) override {
    std::string value = pCharacteristic->getValue();

    if (value.length() == 0) return;

    String cmd = "";
    for (char c : value) {
      cmd += (char)toupper(c);
    }

    if (cmd == "1" || cmd == "ON") {
      digitalWrite(LED_BUILTIN, HIGH);
      ledState = true;
      Serial.println("LED ON");
      pCharacteristic->setValue("ON");
    } 
    else if (cmd == "0" || cmd == "OFF") {
      digitalWrite(LED_BUILTIN, LOW);
      ledState = false;
      Serial.println("LED OFF");
      pCharacteristic->setValue("OFF");
    } 
    else {
      Serial.print("Unknown command: ");
      Serial.println(cmd);
      pCharacteristic->setValue("Use 1/0 or ON/OFF");
    }
  }
};

void setup() {
  Serial.begin(115200);
  delay(1000);

  pinMode(LED_BUILTIN, OUTPUT);
  digitalWrite(LED_BUILTIN, LOW);

  Serial.println("Starting BLE work!");

  BLEDevice::init("ESP32S3_LED_CTRL");
  BLEServer *pServer = BLEDevice::createServer();
  BLEService *pService = pServer->createService(SERVICE_UUID);

  pCharacteristic = pService->createCharacteristic(
    CHARACTERISTIC_UUID,
    BLECharacteristic::PROPERTY_READ |
    BLECharacteristic::PROPERTY_WRITE
  );

  pCharacteristic->setValue("OFF");
  pCharacteristic->setCallbacks(new MyCharacteristicCallbacks());

  pService->start();

  BLEAdvertising *pAdvertising = BLEDevice::getAdvertising();
  pAdvertising->addServiceUUID(SERVICE_UUID);
  pAdvertising->setScanResponse(true);
  BLEDevice::startAdvertising();

  Serial.println("BLE ready. Write 1/0 or ON/OFF to control LED.");
}

void loop() {
  delay(2000);
}
