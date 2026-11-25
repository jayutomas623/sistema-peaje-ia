import 'package:flutter/foundation.dart';
import 'package:google_sign_in/google_sign_in.dart';

import '../config.dart';
import 'api.dart';

/// Resultado del intento de inicio de sesión.
enum ResultadoLogin { ok, denegado, cancelado, error }

/// Sesión del operador: Google Sign-In (ID Token) -> Laravel /auth/google -> token Sanctum.
class Sesion extends ChangeNotifier {
  Sesion._();
  static final Sesion instancia = Sesion._();

  Map<String, dynamic>? usuario;
  String? emailDenegado;
  String? ultimoError;
  bool _googleIniciado = false;

  bool get autenticado => usuario != null;
  String get rol => (usuario?['rol'] ?? '') as String;

  Future<void> _iniciarGoogle() async {
    if (_googleIniciado) return;
    await GoogleSignIn.instance.initialize(
      serverClientId: Config.googleServerClientId.isEmpty ? null : Config.googleServerClientId,
    );
    _googleIniciado = true;
  }

  /// Restaura la sesión guardada (token en almacenamiento seguro).
  Future<void> restaurar() async {
    await Api.instancia.cargar();
    if (!Api.instancia.tieneToken) return;
    try {
      final d = await Api.instancia.yo();
      usuario = d['usuario'] as Map<String, dynamic>?;
    } on ApiError catch (e) {
      if (e.noAutenticado || e.accesoDenegado) await Api.instancia.guardarToken(null);
    } catch (_) {
      // sin red: se pedirá login de nuevo si el token no sirve
    }
    notifyListeners();
  }

  Future<ResultadoLogin> loginConGoogle() async {
    ultimoError = null;
    emailDenegado = null;
    try {
      await _iniciarGoogle();
      final cuenta = await GoogleSignIn.instance.authenticate();
      final idToken = cuenta.authentication.idToken;
      if (idToken == null) {
        ultimoError = 'Google no devolvió un ID Token. Revise GOOGLE_SERVER_CLIENT_ID (Web Client ID).';
        return ResultadoLogin.error;
      }
      final d = await Api.instancia.loginGoogle(idToken);
      usuario = d['usuario'] as Map<String, dynamic>?;
      notifyListeners();
      return ResultadoLogin.ok;
    } on GoogleSignInException catch (e) {
      if (e.code == GoogleSignInExceptionCode.canceled) return ResultadoLogin.cancelado;
      ultimoError = 'Google Sign-In: ${e.description ?? e.code.name}';
      return ResultadoLogin.error;
    } on ApiError catch (e) {
      if (e.accesoDenegado) {
        emailDenegado = e.datos?['email']?.toString();
        await GoogleSignIn.instance.signOut();
        return ResultadoLogin.denegado;
      }
      ultimoError = e.mensaje;
      return ResultadoLogin.error;
    } catch (e) {
      ultimoError = 'No se pudo conectar con ${Api.instancia.baseUrl}: $e';
      return ResultadoLogin.error;
    }
  }

  Future<void> cerrarSesion() async {
    await Api.instancia.logout();
    try {
      await _iniciarGoogle();
      await GoogleSignIn.instance.signOut();
    } catch (_) {}
    usuario = null;
    notifyListeners();
  }
}
