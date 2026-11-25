import 'dart:async';
import 'dart:io';

import 'package:camera/camera.dart';
import 'package:flutter/material.dart';

import '../config.dart';
import '../servicios/api.dart';
import '../servicios/sonido.dart';

/// MODO CASETA: cámara en vivo + envío de frames (2–5 fps) a POST /api/v1/pasos/frame.
/// Control de concurrencia: nunca hay más de UNA petición en vuelo; los ticks que llegan mientras
/// tanto se descartan (no se apilan frames ni se satura la red ni la GPU de RunPod).
class CasetaPage extends StatefulWidget {
  const CasetaPage({super.key, required this.activa});
  final bool activa;

  @override
  State<CasetaPage> createState() => _CasetaPageState();
}

class _CasetaPageState extends State<CasetaPage> with WidgetsBindingObserver {
  CameraController? _camara;
  List<Caseta> _casetas = [];
  Caseta? _caseta;
  bool _capturando = false;
  bool _enVuelo = false;
  double _fps = 2;
  Timer? _timer;
  Map<String, dynamic>? _paso;        // último paso registrado
  String _estadoTexto = 'Detenido';
  String? _error;
  int _enviados = 0, _registrados = 0, _descartados = 0;
  double? _latenciaMs;
  Color? _flash;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _iniciar();
  }

  Future<void> _iniciar() async {
    try {
      _casetas = await Api.instancia.casetas();
      if (_casetas.isNotEmpty) _caseta = _casetas.first;
    } catch (e) {
      _error = 'No se pudieron cargar las casetas: $e';
    }
    await _abrirCamara();
    if (mounted) setState(() {});
  }

  Future<void> _abrirCamara() async {
    try {
      final camaras = await availableCameras();
      final trasera = camaras.firstWhere((c) => c.lensDirection == CameraLensDirection.back, orElse: () => camaras.first);
      final c = CameraController(trasera, ResolutionPreset.high, enableAudio: false, imageFormatGroup: ImageFormatGroup.jpeg);
      await c.initialize();
      await c.setFlashMode(FlashMode.off);
      _camara = c;
    } catch (e) {
      _error = 'Cámara no disponible: $e';
    }
  }

  @override
  void didUpdateWidget(CasetaPage old) {
    super.didUpdateWidget(old);
    if (!widget.activa && _capturando) _detener();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    final c = _camara;
    if (c == null || !c.value.isInitialized) return;
    if (state == AppLifecycleState.inactive || state == AppLifecycleState.paused) {
      _detener();
      c.dispose();
      _camara = null;
    } else if (state == AppLifecycleState.resumed) {
      _abrirCamara().then((_) => mounted ? setState(() {}) : null);
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _timer?.cancel();
    _camara?.dispose();
    super.dispose();
  }

  // ------------------------------------------------------------------ captura
  void _iniciarCaptura() {
    if (_caseta == null || _camara == null) return;
    setState(() {
      _capturando = true;
      _estadoTexto = 'Buscando vehículo…';
    });
    _reprogramar();
  }

  void _reprogramar() {
    _timer?.cancel();
    if (!_capturando) return;
    _timer = Timer.periodic(Duration(milliseconds: (1000 / _fps).round()), (_) {
      if (_enVuelo) {
        _descartados++;           // throttling: se omite el tick si hay una inferencia en curso
        return;
      }
      _capturar('auto');
    });
  }

  void _detener() {
    _timer?.cancel();
    if (mounted) {
      setState(() {
        _capturando = false;
        _estadoTexto = 'Detenido';
      });
    }
  }

  Future<void> _capturar(String modo) async {
    final c = _camara;
    if (c == null || !c.value.isInitialized || _caseta == null || _enVuelo || c.value.isTakingPicture) return;
    _enVuelo = true;
    final t0 = DateTime.now();
    try {
      final foto = await c.takePicture();
      final bytes = await foto.readAsBytes();
      unawaited(File(foto.path).delete().catchError((_) => File(foto.path)));   // no se guardan frames en el teléfono
      _enviados++;
      final r = await Api.instancia.enviarFrame(bytes, _caseta!.id, modo: modo);
      _latenciaMs = DateTime.now().difference(t0).inMilliseconds.toDouble();
      _procesar(r, modo);
      _error = null;
    } on ApiError catch (e) {
      _error = e.mensaje;
    } catch (e) {
      _error = 'Error de red: $e';
    } finally {
      _enVuelo = false;
      if (mounted) setState(() {});
    }
  }

  void _procesar(Map<String, dynamic> r, String modo) {
    final paso = r['paso'] as Map<String, dynamic>?;
    if (r['registrado'] == true && paso != null) {
      _registrados++;
      _paso = paso;
      final ok = paso['estado'] == 'autorizado';
      _estadoTexto = ok ? 'Paso autorizado' : (paso['estado_texto'] ?? 'Alerta').toString();
      _destello(ok ? Colors.green : Colors.red);
      ok ? Sonido.instancia.ok() : Sonido.instancia.alerta();
    } else if (r['duplicado'] == true && paso != null) {
      _paso = paso;
      _estadoTexto = 'Mismo vehículo (ya registrado)';
    } else {
      final ia = r['inferencia'] as Map<String, dynamic>?;
      final veh = ia?['vehiculo'] as Map<String, dynamic>?;
      _estadoTexto = switch (r['motivo']) {
        'placa_no_legible_aun' => 'Vehículo ${veh?['categoria'] ?? ''} detectado · acercando placa…',
        'sin_vehiculo' => 'Buscando vehículo…',
        'error_inferencia' => 'Servidor de inferencia no disponible',
        _ => 'Sin resultado',
      };
      if (modo == 'manual') _destello(Colors.orange);
    }
  }

  void _destello(Color c) {
    setState(() => _flash = c.withValues(alpha: 0.35));
    Future.delayed(const Duration(milliseconds: 700), () => mounted ? setState(() => _flash = null) : null);
  }

  // ------------------------------------------------------------------ UI
  @override
  Widget build(BuildContext context) {
    final c = _camara;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Modo Caseta'),
        actions: [
          if (_latenciaMs != null)
            Padding(padding: const EdgeInsets.only(right: 12), child: Center(child: Text('${_latenciaMs!.round()} ms'))),
        ],
      ),
      body: Column(children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 4, 12, 4),
          child: DropdownButtonFormField<Caseta>(
            initialValue: _caseta,
            isExpanded: true,
            decoration: const InputDecoration(labelText: 'Caseta', border: OutlineInputBorder(), isDense: true),
            items: _casetas.map((x) => DropdownMenuItem(value: x, child: Text(x.nombre, overflow: TextOverflow.ellipsis))).toList(),
            onChanged: _capturando ? null : (v) => setState(() => _caseta = v),
          ),
        ),
        Expanded(
          child: Stack(fit: StackFit.expand, children: [
            Container(color: Colors.black),
            if (c != null && c.value.isInitialized)
              Center(child: AspectRatio(aspectRatio: 1 / c.value.aspectRatio, child: CameraPreview(c)))
            else
              Center(child: Text(_error ?? 'Iniciando cámara…', style: const TextStyle(color: Colors.white))),
            if (_flash != null) Container(color: _flash),
            Positioned(top: 8, left: 8, right: 8, child: _barraEstado()),
            if (_paso != null) Positioned(left: 8, right: 8, bottom: 8, child: _tarjetaPaso(_paso!)),
          ]),
        ),
        _controles(),
      ]),
    );
  }

  Widget _barraEstado() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(color: Colors.black54, borderRadius: BorderRadius.circular(10)),
      child: Row(children: [
        Icon(_capturando ? Icons.fiber_manual_record : Icons.pause_circle, color: _capturando ? Colors.redAccent : Colors.white70, size: 16),
        const SizedBox(width: 6),
        Expanded(child: Text(_error ?? _estadoTexto, style: const TextStyle(color: Colors.white), overflow: TextOverflow.ellipsis)),
        if (_enVuelo) const SizedBox(width: 14, height: 14, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)),
        const SizedBox(width: 8),
        Text('$_enviados↑ $_registrados✓ $_descartados⏭', style: const TextStyle(color: Colors.white70, fontSize: 11)),
      ]),
    );
  }

  Widget _tarjetaPaso(Map<String, dynamic> p) {
    final estado = (p['estado'] ?? '').toString();
    final color = switch (estado) {
      'autorizado' => Colors.green.shade700,
      'sin_saldo' => Colors.orange.shade800,
      'placa_ilegible' => Colors.grey.shade700,
      _ => Colors.red.shade700,
    };
    Widget dato(String t, String v) => Column(children: [
          Text(t, style: const TextStyle(fontSize: 11, color: Colors.black54)),
          Text(v, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
        ]);
    return Card(
      elevation: 6,
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Row(children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(border: Border.all(color: const Color(0xFF1D4ED8), width: 2), borderRadius: BorderRadius.circular(6)),
              child: Text(p['placa']?.toString() ?? 'ILEGIBLE',
                  style: const TextStyle(fontFamily: 'monospace', fontWeight: FontWeight.w900, fontSize: 22, letterSpacing: 2)),
            ),
            const Spacer(),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(6)),
              child: Text((p['estado_texto'] ?? estado).toString(), style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
            ),
          ]),
          const SizedBox(height: 10),
          Row(mainAxisAlignment: MainAxisAlignment.spaceAround, children: [
            dato('Categoría', (p['categoria'] ?? '—').toString()),
            dato('Ejes', (p['ejes'] ?? '—').toString()),
            dato('Pasajeros', (p['pasajeros'] ?? '—').toString()),
            dato('Tarifa', 'Bs ${((p['monto'] ?? 0) as num).toStringAsFixed(2)}'),
          ]),
          if (p['propietario'] != null || p['saldo'] != null) ...[
            const SizedBox(height: 6),
            Text('${p['propietario'] ?? ''}${p['saldo'] != null ? ' · saldo Bs ${(p['saldo'] as num).toStringAsFixed(2)}' : ''}',
                style: const TextStyle(fontSize: 12, color: Colors.black54), overflow: TextOverflow.ellipsis),
          ],
        ]),
      ),
    );
  }

  Widget _controles() {
    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 6, 12, 8),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Row(children: [
            const Text('Frecuencia'),
            Expanded(
              child: Slider(
                value: _fps,
                min: Config.fpsMin.toDouble(),
                max: Config.fpsMax.toDouble(),
                divisions: Config.fpsMax - Config.fpsMin,
                label: '${_fps.round()} fps',
                onChanged: (v) {
                  setState(() => _fps = v);
                  _reprogramar();
                },
              ),
            ),
            Text('${_fps.round()} fps'),
          ]),
          Row(children: [
            Expanded(
              child: FilledButton.icon(
                style: FilledButton.styleFrom(backgroundColor: _capturando ? Colors.red : null, padding: const EdgeInsets.symmetric(vertical: 14)),
                onPressed: (_camara == null || _caseta == null) ? null : (_capturando ? _detener : _iniciarCaptura),
                icon: Icon(_capturando ? Icons.stop : Icons.play_arrow),
                label: Text(_capturando ? 'Detener captura' : 'Iniciar captura'),
              ),
            ),
            const SizedBox(width: 10),
            OutlinedButton.icon(
              style: OutlinedButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 14)),
              onPressed: (_camara == null || _caseta == null || _enVuelo) ? null : () => _capturar('manual'),
              icon: const Icon(Icons.camera),
              label: const Text('Captura manual'),
            ),
          ]),
        ]),
      ),
    );
  }
}
