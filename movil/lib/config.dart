/// Configuración inyectada al compilar (no hay secretos aquí):
///
/// flutter build apk --release \
///   --dart-define=API_BASE_URL=https://peaje.tu-dominio.com \
///   --dart-define=GOOGLE_SERVER_CLIENT_ID=xxxx.apps.googleusercontent.com
class Config {
  /// Subdominio HTTPS de producción (editable luego en Ajustes sin recompilar).
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://peaje.tu-dominio.com',
  );

  /// Web Client ID de Google Cloud (OAuth). Es público: identifica al backend que valida el ID Token.
  static const String googleServerClientId = String.fromEnvironment('GOOGLE_SERVER_CLIENT_ID');

  static const int fpsMin = 2;
  static const int fpsMax = 5;
}
