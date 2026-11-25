import 'dart:math';
import 'dart:typed_data';

import 'package:audioplayers/audioplayers.dart';
import 'package:flutter/services.dart';

/// Sonidos generados en memoria (WAV PCM 16 bits) — sin archivos de audio en el APK.
class Sonido {
  Sonido._();
  static final Sonido instancia = Sonido._();
  final AudioPlayer _player = AudioPlayer();

  late final Uint8List _ok = _wav([(1800, 120)]);
  late final Uint8List _alerta = _wav([(950, 180), (0, 90), (950, 180), (0, 90), (950, 180)]);

  Future<void> ok() => _reproducir(_ok, vibrar: false);
  Future<void> alerta() => _reproducir(_alerta, vibrar: true);

  Future<void> _reproducir(Uint8List datos, {required bool vibrar}) async {
    try {
      if (vibrar) await HapticFeedback.heavyImpact();
      await _player.stop();
      await _player.play(BytesSource(datos, mimeType: 'audio/wav'));
    } catch (_) {
      // el sonido nunca debe interrumpir la operación de la caseta
    }
  }

  /// Tonos (frecuencia Hz, duración ms); frecuencia 0 = silencio.
  static Uint8List _wav(List<(int, int)> tonos, {int muestreo = 22050}) {
    final muestras = <int>[];
    for (final (f, ms) in tonos) {
      final n = muestreo * ms ~/ 1000;
      for (var i = 0; i < n; i++) {
        final env = min(1.0, min(i, n - i) / (muestreo * 0.005));     // rampa anti-clic de 5 ms
        muestras.add(f == 0 ? 0 : (sin(2 * pi * f * i / muestreo) * 12000 * env).round());
      }
    }
    final datos = ByteData(44 + muestras.length * 2);
    void txt(int o, String s) => s.codeUnits.asMap().forEach((i, c) => datos.setUint8(o + i, c));
    txt(0, 'RIFF');
    datos.setUint32(4, 36 + muestras.length * 2, Endian.little);
    txt(8, 'WAVE');
    txt(12, 'fmt ');
    datos.setUint32(16, 16, Endian.little);
    datos.setUint16(20, 1, Endian.little);
    datos.setUint16(22, 1, Endian.little);
    datos.setUint32(24, muestreo, Endian.little);
    datos.setUint32(28, muestreo * 2, Endian.little);
    datos.setUint16(32, 2, Endian.little);
    datos.setUint16(34, 16, Endian.little);
    txt(36, 'data');
    datos.setUint32(40, muestras.length * 2, Endian.little);
    for (var i = 0; i < muestras.length; i++) {
      datos.setInt16(44 + i * 2, muestras[i], Endian.little);
    }
    return datos.buffer.asUint8List();
  }
}
