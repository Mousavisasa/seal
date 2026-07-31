#include <Adafruit_NeoPixel.h>
#include <BLEDevice.h>
#include <BLEServer.h>
#include <BLEUtils.h>
#include <BLE2902.h>
#include <WiFi.h>
#include <ThingerESP32.h>

// Thinger configuration
#define USERNAME           "aliZee"
#define DEVICE_ID          "LED_Test"
#define DEVICE_CREDENTIAL  "MOOdozMKyMXVcQ!f"
#define SSID               "Xiaomi 11T"
#define SSID_PASSWORD      "123456789012"

ThingerESP32 thing(USERNAME, DEVICE_ID, DEVICE_CREDENTIAL);

/* --- NeoPixel --- */
#define LED_PIN    48
#define LED_COUNT  1
Adafruit_NeoPixel pixel(LED_COUNT, LED_PIN, NEO_GRB + NEO_KHZ800);

/* --- BLE --- */
#define SERVICE_UUID        "12345678-1234-1234-1234-123456789abc"
#define CHARACTERISTIC_UUID "abcdefab-cdef-abcd-efab-cdefabcdefab"

BLECharacteristic *pCharacteristic;
String bleBuffer = "";
bool bleDataReady = false;

/* --- ثابت‌ها --- */
const int    GlobalID  = 1;
const String MASTERKEY = "123456";

/* --- ساختار داده --- */
struct AuthData {
  String lat, lon, user, pass;
};

AuthData driverOrigin, operatorOrigin, driverDest, operatorDest;

/* --- وضعیت‌ها --- */
enum SystemMode {
  DRIVER_ORIGIN,
  OPERATOR_ORIGIN,
  MODE_SLEEP,
  DRIVER_DEST,
  OPERATOR_DEST
};

SystemMode currentMode = DRIVER_ORIGIN;
bool isLocked = false;

/* --- رنگ نئوپیکسل --- */
void setPixelForMode(SystemMode mode) {
  uint32_t c;
  switch (mode) {
    case DRIVER_ORIGIN:   c = pixel.Color(235, 235, 235); break;
    case OPERATOR_ORIGIN: c = pixel.Color(0, 187, 255);   break;
    case MODE_SLEEP:      c = pixel.Color(0, 100, 0);     break;
    case DRIVER_DEST:     c = pixel.Color(246, 255, 0);   break;
    case OPERATOR_DEST:   c = pixel.Color(134, 16, 181);  break;
    default:              c = pixel.Color(0, 0, 0);       break;
  }
  pixel.setPixelColor(0, c);
  pixel.show();
}

/* --- نمایش وضعیت --- */
void showStatus(const char* text) {
  Serial.println("--------------------------------");
  Serial.print("[MODE] "); Serial.println(text);
  Serial.print("[LOCK] "); Serial.println(isLocked ? "CLOSED" : "OPEN");
  Serial.print("BT: SmartSeal"); Serial.println(GlobalID);
  Serial.println("--------------------------------");
}

void controlLock(bool lockIt) {
  isLocked = lockIt;
  Serial.println(lockIt ? "[LOCK] CLOSED" : "[LOCK] OPEN");
}

/* --- پارس فرمان --- */
bool parseCommand(String cmd, AuthData &out) {
  int p1 = cmd.indexOf('|');
  int p2 = cmd.indexOf('|', p1 + 1);
  int p3 = cmd.indexOf('|', p2 + 1);

  if (!(p1 > 0 && p2 > p1 && p3 > p2)) {
    Serial.println("[ERROR] Invalid command format");
    return false;
  }

  out.lat  = cmd.substring(0, p1);
  out.lon  = cmd.substring(p1 + 1, p2);
  out.user = cmd.substring(p2 + 1, p3);
  out.pass = cmd.substring(p3 + 1);
  return true;
}

bool checkMasterKey(const AuthData &data) {
  if (data.pass != MASTERKEY) {
    Serial.println("[AUTH] MASTER KEY INVALID");
    return false;
  }
  return true;
}

/* --- پردازش فرمان --- */
void processCommand(String cmd) {
  AuthData temp;

  if (currentMode == MODE_SLEEP) {
    if (cmd == "BTN_Simulation") {
      currentMode = DRIVER_DEST;
      showStatus("Driver|Destination");
      setPixelForMode(currentMode);
    } else {
      Serial.println("[INFO] IN TRANSIT - send BTN_Simulation to continue");
    }
    return;
  }

  if (!parseCommand(cmd, temp)) return;
  if (!checkMasterKey(temp)) return;

  switch (currentMode) {
    case DRIVER_ORIGIN:
      driverOrigin = temp;
      currentMode = OPERATOR_ORIGIN;
      showStatus("Operator|Origin");
      setPixelForMode(currentMode);
      break;

    case OPERATOR_ORIGIN:
      if (temp.user == driverOrigin.user)
        Serial.println("[WARN] Driver & Operator USER identical at ORIGIN");

      operatorOrigin = temp;
      sendToThinger("origin", driverOrigin, operatorOrigin);  
      controlLock(true);
      currentMode = MODE_SLEEP;
      Serial.println("[INFO] IN TRANSIT - send BTN_Simulation to continue");
      setPixelForMode(currentMode);
      break;

    case DRIVER_DEST:
      if (temp.user != driverOrigin.user) {
        Serial.println("[ERROR] Driver DEST mismatch with ORIGIN");
        break;
      }

      driverDest = temp;
      currentMode = OPERATOR_DEST;
      showStatus("Operator|Destination");
      setPixelForMode(currentMode);
      break;

    case OPERATOR_DEST:
      if (temp.user != operatorOrigin.user) {
        Serial.println("[ERROR] Operator DEST mismatch with ORIGIN");
        break;
      }

      operatorDest = temp;
      sendToThinger("destination", driverDest, operatorDest);
      controlLock(false);
      currentMode = DRIVER_ORIGIN;
      showStatus("Driver|Origin");
      setPixelForMode(currentMode);
      break;
  }
}

/* --- BLE Callback --- */
class AuthCallback : public BLECharacteristicCallbacks {
  void onWrite(BLECharacteristic *pChar) override {
    bleBuffer = pChar->getValue().c_str();
    bleBuffer.trim();
    bleDataReady = true;
    Serial.print("[BLE] Received: ");
    Serial.println(bleBuffer);
  }
};
void sendToThinger(String stage, AuthData driver, AuthData oper) {

  pson data;

  data["stage"] = stage.c_str();

  data["driver"]["lat"]  = driver.lat.c_str();
  data["driver"]["lon"]  = driver.lon.c_str();
  data["driver"]["user"] = driver.user.c_str();

  data["operator"]["lat"]  = oper.lat.c_str();
  data["operator"]["lon"]  = oper.lon.c_str();
  data["operator"]["user"] = oper.user.c_str();

  thing.write_bucket("serial_bucket", data);

  Serial.println("[THINGER] Data Sent");
}

/* --- setup --- */
void setup() {
  Serial.begin(115200);
  thing.add_wifi(SSID, SSID_PASSWORD);
  pixel.begin();
  pixel.setBrightness(50);

  /* BLE init */
  BLEDevice::init(std::string("SmartSeal") + std::to_string(GlobalID));
  BLEServer   *pServer  = BLEDevice::createServer();
  BLEService  *pService = pServer->createService(SERVICE_UUID);

  pCharacteristic = pService->createCharacteristic(
    CHARACTERISTIC_UUID,
    BLECharacteristic::PROPERTY_WRITE | BLECharacteristic::PROPERTY_NOTIFY
  );
  pCharacteristic->addDescriptor(new BLE2902());
  pCharacteristic->setCallbacks(new AuthCallback());

  pService->start();
  BLEDevice::getAdvertising()->start();
  Serial.println("[BLE] Advertising started");

  controlLock(false);
  showStatus("Driver|Origin");
  setPixelForMode(currentMode);
}

/* --- loop --- */
void loop() {
  thing.handle();
  /* ورودی BLE */
  if (bleDataReady) {
    bleDataReady = false;
    processCommand(bleBuffer);
  }

  /* ورودی Serial (BTN_Simulation و دیباگ) */
  if (Serial.available()) {
    String cmd = Serial.readStringUntil('\n');
    cmd.trim();
    processCommand(cmd);
  }
}
