import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../config.dart';

/// Error de la API con su código HTTP (403 = acceso denegado por lista blanca).
class ApiError implements Exception {
  ApiError(this.status, this.mensaje, [this.datos]);
  final int status;
  final String mensaje;
  final Map<String, dynamic>? datos;

  bool get accesoDenegado => status == 403;
  bool get noAutenticado => status == 401;

  @override
  String toString() => mensaje;
}

class Caseta {
  Caseta(this.id, this.codigo, this.nombre);
  final int id;
  final String codigo;
  final String nombre;

  factory Caseta.desdeJson(Map<String, dynamic> j) =>
      Caseta(j['id'] as int, (j['codigo'] ?? '') as String, (j['nombre'] ?? '') as String);
}

/// Cliente de la API REST de Laravel (/api/v1). Solo HTTPS en producción, sin recursos locales.
class Api {
  Api._();
  static final Api instancia = Api._();

  static const _claveUrl = 'api_base_url';
  static const _claveToken = 'token_sanctum';
  final _seguro = const FlutterSecureStorage();

  String _baseUrl = Config.apiBaseUrl;
  String? _token;

  String get baseUrl => _baseUrl;
  bool get tieneToken => _token != null && _token!.isNotEmpty;

  Future<void> cargar() async {
    final prefs = await SharedPreferences.getInstance();
    _baseUrl = _limpiarUrl(prefs.getString(_claveUrl) ?? Config.apiBaseUrl);
    _token = await _seguro.read(key: _claveToken);
  }

  Future<void> cambiarBaseUrl(String url) async {
    _baseUrl = _limpiarUrl(url);
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_claveUrl, _baseUrl);
  }

  String _limpiarUrl(String u) => u.trim().replaceAll(RegExp(r'/+$'), '');

  Future<void> guardarToken(String? token) async {
    _token = token;
    if (token == null) {
      await _seguro.delete(key: _claveToken);
    } else {
      await _seguro.write(key: _claveToken, value: token);
    }
  }

  Uri _uri(String ruta) => Uri.parse('$_baseUrl/api/v1$ruta');

  Map<String, String> get _cabeceras => {
        'Accept': 'application/json',
        if (tieneToken) 'Authorization': 'Bearer $_token',
      };

  Map<String, dynamic> _procesar(http.Response r) {
    Map<String, dynamic> cuerpo = {};
    try {
      final d = jsonDecode(utf8.decode(r.bodyBytes));
      if (d is Map<String, dynamic>) cuerpo = d;
    } catch (_) {}
    if (r.statusCode >= 200 && r.statusCode < 300) return cuerpo;
    throw ApiError(r.statusCode, (cuerpo['message'] ?? 'Error HTTP ${r.statusCode}').toString(), cuerpo);
  }

  // ------------------------------------------------------------------ autenticación
  /// Envía el ID Token de Google; el backend valida la lista blanca (403 si no está autorizado).
  Future<Map<String, dynamic>> loginGoogle(String idToken) async {
    final r = await http
        .post(_uri('/auth/google'),
            headers: {..._cabeceras, 'Content-Type': 'application/json'},
            body: jsonEncode({'id_token': idToken, 'dispositivo': 'app-movil'}))
        .timeout(const Duration(seconds: 20));
    final d = _procesar(r);
    await guardarToken(d['token'] as String?);
    return d;
  }

  Future<Map<String, dynamic>> yo() async =>
      _procesar(await http.get(_uri('/auth/yo'), headers: _cabeceras).timeout(const Duration(seconds: 15)));

  Future<void> logout() async {
    try {
      await http.post(_uri('/auth/logout'), headers: _cabeceras).timeout(const Duration(seconds: 8));
    } catch (_) {}
    await guardarToken(null);
  }

  /// Comprueba que el subdominio responde (ruta /up de Laravel).
  Future<bool> probarConexion() async {
    try {
      final r = await http.get(Uri.parse('$_baseUrl/up')).timeout(const Duration(seconds: 10));
      return r.statusCode == 200;
    } catch (_) {
      return false;
    }
  }

  // ------------------------------------------------------------------ caseta
  Future<List<Caseta>> casetas() async {
    final d = _procesar(await http.get(_uri('/casetas'), headers: _cabeceras).timeout(const Duration(seconds: 15)));
    return ((d['casetas'] ?? []) as List).map((e) => Caseta.desdeJson(e as Map<String, dynamic>)).toList();
  }

  /// POST /pasos/frame — modo 'auto' (video 2–5 fps) o 'manual' (botón de captura).
  Future<Map<String, dynamic>> enviarFrame(Uint8List jpeg, int casetaId, {String modo = 'auto'}) async {
    final req = http.MultipartRequest('POST', _uri('/pasos/frame'))
      ..headers.addAll(_cabeceras)
      ..fields['caseta_id'] = '$casetaId'
      ..fields['modo'] = modo
      ..files.add(http.MultipartFile.fromBytes('imagen', jpeg, filename: 'frame.jpg'));
    final resp = await http.Response.fromStream(await req.send().timeout(const Duration(seconds: 40)));
    return _procesar(resp);
  }

  // ------------------------------------------------------------------ agente
  Future<Map<String, dynamic>> chat({String? mensaje, Uint8List? imagen, List<Map<String, String>> historial = const []}) async {
    final req = http.MultipartRequest('POST', _uri('/agente/chat'))..headers.addAll(_cabeceras);
    if (mensaje != null && mensaje.trim().isNotEmpty) req.fields['mensaje'] = mensaje.trim();
    if (historial.isNotEmpty) req.fields['historial'] = jsonEncode(historial);
    if (imagen != null) req.files.add(http.MultipartFile.fromBytes('imagen', imagen, filename: 'placa.jpg'));
    final resp = await http.Response.fromStream(await req.send().timeout(const Duration(seconds: 60)));
    return _procesar(resp);
  }
}
