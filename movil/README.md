# movil/ — App Flutter (Android)

* **Login exclusivo con Google** (`google_sign_in` 7.x) → `POST /api/v1/auth/google` → pantalla **Acceso denegado** si HTTP 403.
* **Modo Caseta:** selector de caseta, cámara en vivo, captura automática 2–5 fps con **una sola petición en vuelo**
  (los ticks sobrantes se descartan), botón de captura manual, tarjeta con placa · categoría · ejes · pasajeros ·
  tarifa Bs · estado, destello y sonido de alerta. Las fotos se borran del teléfono tras enviarse.
* **Modo Consulta:** chat con el agente (texto, cámara o galería), estado 1–4 y sugerencias "¿Quisiste decir…?".
* **Ajustes:** URL HTTPS del subdominio editable sin recompilar + verificación, datos del usuario y cerrar sesión.

```bash
flutter pub get && flutter analyze && flutter test
flutter build apk --release \
  --dart-define=API_BASE_URL=https://peaje.tu-dominio.com \
  --dart-define=GOOGLE_SERVER_CLIENT_ID=<WEB_CLIENT_ID>.apps.googleusercontent.com
```

Firma release: `android/key.properties` + `upload-keystore.jks` (ambos fuera de git). Sin ellos se firma con la
clave debug. La SHA-1 del certificado debe registrarse en el cliente OAuth **Android** de Google Cloud
(ver `../GUIA_PASO_A_PASO_POST_DESARROLLO.md`, Paso 4).
