/*
 * =====================================================================================
 *  ESP32 · Dispositivo de alertas del Sistema Inteligente de Control de Peaje (Bono +5)
 * =====================================================================================
 *  Consulta cada 1 s por HTTPS el backend en la NUBE (cero dependencia de una PC local):
 *      GET https://<API_HOST>/api/v1/hardware/ultimo-evento   (header X-Hardware-Token)
 *      -> {"id":123,"tipo_senal":"idle|inferencia|ok|alerta","placa":"2481KLM","monto":25,"timestamp":"..."}
 *
 *  Señales:
 *    inferencia -> LED AZUL parpadea (inferencia en curso en RunPod)
 *    ok         -> LED VERDE fijo 3 s + 1 beep corto (paso autorizado)
 *    alerta     -> LED ROJO parpadeo rápido + 3 beeps (no registrada / sin saldo / ilegible)
 *    sin Wi-Fi  -> LED ROJO parpadeo lento
 *
 *  Placa: ESP32 DevKit V1 (38 pines). Pines: Azul GPIO25, Verde GPIO26, Rojo GPIO27, Buzzer GPIO14.
 *  Librerías: ArduinoJson 7.x (Gestor de librerías). Core: esp32 by Espressif 2.0.x o 3.x.
 *  Credenciales en secrets.h (NO se versiona; copie secrets.h.example).
 * =====================================================================================
 */
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include "secrets.h"

#define FW_VERSION "1.0.0"

// ---------------- Pines (ver DIAGRAMA_CONEXION.md) ----------------
const int PIN_LED_AZUL  = 25;
const int PIN_LED_VERDE = 26;
const int PIN_LED_ROJO  = 27;
const int PIN_BUZZER    = 14;

// true = buzzer PASIVO (requiere tono PWM); false = buzzer ACTIVO (suena con nivel alto)
#ifndef BUZZER_PASIVO
#define BUZZER_PASIVO true
#endif

const unsigned long INTERVALO_CONSULTA_MS = 1000;
const unsigned long DURACION_OK_MS        = 3000;
const unsigned long DURACION_ALERTA_MS    = 4000;
const unsigned long MAX_INFERENCIA_MS     = 10000;

enum Modo { IDLE, INFERENCIA, OK_PASO, ALERTA, SIN_WIFI };
Modo modo = IDLE;
unsigned long inicioModo = 0;
unsigned long ultimaConsulta = 0;
long ultimoId = -1;               // -1 = aún no sincronizado (no se reproduce el evento viejo al encender)
int fallosSeguidos = 0;

// Cola de beeps no bloqueante
int beepsPendientes = 0;
unsigned long proximoBeep = 0;
unsigned long finBeep = 0;
bool sonando = false;

WiFiClientSecure clienteTls;

// ------------------------------------------------------------------ utilidades
void leds(bool azul, bool verde, bool rojo) {
  digitalWrite(PIN_LED_AZUL, azul);
  digitalWrite(PIN_LED_VERDE, verde);
  digitalWrite(PIN_LED_ROJO, rojo);
}

void buzzer(bool on, int frecuencia) {
  if (BUZZER_PASIVO) {
    if (on) tone(PIN_BUZZER, frecuencia); else noTone(PIN_BUZZER);
  } else {
    digitalWrite(PIN_BUZZER, on ? HIGH : LOW);
  }
}

int frecuenciaBeep = 2000;
int duracionBeep = 120;
void programarBeeps(int n, int frecuencia, int duracionMs) {
  beepsPendientes = n;
  frecuenciaBeep = frecuencia;
  duracionBeep = duracionMs;
  proximoBeep = millis();
}

void atenderBuzzer() {
  unsigned long ahora = millis();
  if (sonando && ahora >= finBeep) {
    buzzer(false, 0);
    sonando = false;
    proximoBeep = ahora + 110;          // silencio entre beeps
  }
  if (!sonando && beepsPendientes > 0 && ahora >= proximoBeep) {
    buzzer(true, frecuenciaBeep);
    sonando = true;
    finBeep = ahora + duracionBeep;
    beepsPendientes--;
  }
}

void cambiarModo(Modo m) {
  modo = m;
  inicioModo = millis();
  if (m == OK_PASO) programarBeeps(1, 2400, 120);            // 1 beep corto
  if (m == ALERTA)  programarBeeps(3, 1200, 180);            // 3 beeps de alarma
}

// ------------------------------------------------------------------ LEDs según modo
void atenderLeds() {
  unsigned long t = millis() - inicioModo;
  switch (modo) {
    case INFERENCIA:
      leds((t / 250) % 2 == 0, false, false);                // azul parpadea 2 Hz
      if (t > MAX_INFERENCIA_MS) cambiarModo(IDLE);
      break;
    case OK_PASO:
      leds(false, true, false);                              // verde fijo 3 s
      if (t > DURACION_OK_MS) cambiarModo(IDLE);
      break;
    case ALERTA:
      leds(false, false, (t / 100) % 2 == 0);                // rojo parpadeo rápido 5 Hz
      if (t > DURACION_ALERTA_MS) cambiarModo(IDLE);
      break;
    case SIN_WIFI:
      leds(false, false, (millis() / 1000) % 2 == 0);        // rojo lento: sin conexión
      break;
    default:
      // idle: pulso azul muy breve cada 5 s (latido del dispositivo)
      leds((millis() % 5000) < 40, false, false);
  }
}

// ------------------------------------------------------------------ red
void conectarWifi() {
  if (WiFi.status() == WL_CONNECTED) return;
  Serial.printf("[wifi] Conectando a %s", WIFI_SSID);
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  unsigned long t0 = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - t0 < 15000) {
    leds(false, false, (millis() / 300) % 2 == 0);
    delay(100);
    Serial.print(".");
  }
  Serial.println();
  if (WiFi.status() == WL_CONNECTED) {
    Serial.printf("[wifi] OK IP=%s RSSI=%d dBm\n", WiFi.localIP().toString().c_str(), WiFi.RSSI());
    if (modo == SIN_WIFI) cambiarModo(IDLE);
  } else {
    Serial.println("[wifi] Sin conexión, se reintentará");
    cambiarModo(SIN_WIFI);
  }
}

void configurarTls() {
  if (strlen(ROOT_CA) > 0) {
    clienteTls.setCACert(ROOT_CA);                           // verificación completa del certificado
    Serial.println("[tls] Verificando certificado con ROOT_CA");
  } else {
    clienteTls.setInsecure();                                // cifrado, sin validar la cadena (solo demo)
    Serial.println("[tls] ADVERTENCIA: ROOT_CA vacío -> setInsecure()");
  }
  clienteTls.setTimeout(8);
}

void consultarEvento() {
  if (WiFi.status() != WL_CONNECTED) { cambiarModo(SIN_WIFI); return; }
  HTTPClient http;
  String url = String("https://") + API_HOST + "/api/v1/hardware/ultimo-evento?caseta_id=" + CASETA_ID +
               "&rssi=" + WiFi.RSSI() + "&fw=" + FW_VERSION;
  if (!http.begin(clienteTls, url)) { Serial.println("[http] begin falló"); return; }
  http.addHeader("X-Hardware-Token", HARDWARE_TOKEN);
  http.addHeader("Accept", "application/json");
  http.setTimeout(5000);
  int codigo = http.GET();
  if (codigo != 200) {
    fallosSeguidos++;
    Serial.printf("[http] HTTP %d (%s)\n", codigo, codigo < 0 ? http.errorToString(codigo).c_str() : "respuesta");
    http.end();
    if (fallosSeguidos >= 5 && modo == IDLE) programarBeeps(1, 600, 60);
    return;
  }
  fallosSeguidos = 0;
  JsonDocument doc;
  DeserializationError err = deserializeJson(doc, http.getStream());
  http.end();
  if (err) { Serial.printf("[json] %s\n", err.c_str()); return; }

  long id = doc["id"] | 0L;
  const char* tipo = doc["tipo_senal"] | "idle";
  const char* placa = doc["placa"] | "";
  float monto = doc["monto"] | 0.0f;

  if (ultimoId < 0) { ultimoId = id; Serial.printf("[sync] último evento id=%ld\n", id); return; }
  if (id == ultimoId) return;                                // nada nuevo
  ultimoId = id;
  Serial.printf("[evento] id=%ld señal=%s placa=%s monto=Bs %.2f\n", id, tipo, placa, monto);

  if (strcmp(tipo, "inferencia") == 0)      cambiarModo(INFERENCIA);
  else if (strcmp(tipo, "ok") == 0)         cambiarModo(OK_PASO);
  else if (strcmp(tipo, "alerta") == 0)     cambiarModo(ALERTA);
  else if (modo == INFERENCIA)              cambiarModo(IDLE);
}

// ------------------------------------------------------------------ ciclo principal
void setup() {
  Serial.begin(115200);
  delay(200);
  pinMode(PIN_LED_AZUL, OUTPUT);
  pinMode(PIN_LED_VERDE, OUTPUT);
  pinMode(PIN_LED_ROJO, OUTPUT);
  pinMode(PIN_BUZZER, OUTPUT);
  Serial.printf("\n== Peaje IA · ESP32 alertas fw %s ==\nBackend: https://%s\n", FW_VERSION, API_HOST);

  // Autoprueba: azul, verde, rojo + beep
  leds(true, false, false); delay(250);
  leds(false, true, false); delay(250);
  leds(false, false, true); delay(250);
  leds(false, false, false);
  buzzer(true, 2000); delay(80); buzzer(false, 0);

  conectarWifi();
  configurarTls();
}

void loop() {
  unsigned long ahora = millis();
  if (WiFi.status() != WL_CONNECTED && (ahora - ultimaConsulta > 5000)) {
    ultimaConsulta = ahora;
    conectarWifi();
  } else if (ahora - ultimaConsulta >= INTERVALO_CONSULTA_MS) {
    ultimaConsulta = ahora;
    consultarEvento();
  }
  atenderLeds();
  atenderBuzzer();
  delay(5);
}
