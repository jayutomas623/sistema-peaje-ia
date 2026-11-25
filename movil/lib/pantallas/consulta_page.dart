import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../servicios/api.dart';

class _Mensaje {
  _Mensaje(this.deUsuario, this.texto, {this.imagen, this.estadoCodigo, this.sugerencias = const [], this.fuente});
  final bool deUsuario;
  final String texto;
  final Uint8List? imagen;
  final int? estadoCodigo;
  final List<String> sugerencias;
  final String? fuente;
}

/// MODO CONSULTA: chat con el agente (POST /api/v1/agente/chat) con texto y/o foto de placa.
class ConsultaPage extends StatefulWidget {
  const ConsultaPage({super.key});

  @override
  State<ConsultaPage> createState() => _ConsultaPageState();
}

class _ConsultaPageState extends State<ConsultaPage> {
  final _texto = TextEditingController();
  final _scroll = ScrollController();
  final _picker = ImagePicker();
  final List<_Mensaje> _mensajes = [
    _Mensaje(false, 'Hola 👋 Soy el Agente Peaje. Escribe una placa (ej. 1151DKL) o toma una foto de una placa.'),
  ];
  bool _enviando = false;

  static const _estados = {
    1: (Colors.green, 'Registrada con paso'),
    2: (Colors.blue, 'Registrada sin paso'),
    3: (Colors.red, 'No registrada · posible evasión'),
    4: (Colors.grey, 'No existe'),
  };

  Future<void> _enviar({String? texto, Uint8List? imagen}) async {
    texto = texto?.trim();
    if ((texto == null || texto.isEmpty) && imagen == null) return;
    final historial = _mensajes
        .where((m) => m.texto.isNotEmpty)
        .map((m) => {'rol': m.deUsuario ? 'usuario' : 'agente', 'texto': m.texto})
        .toList();
    setState(() {
      _mensajes.add(_Mensaje(true, texto?.isNotEmpty == true ? texto! : '(foto de placa)', imagen: imagen));
      _enviando = true;
    });
    _texto.clear();
    _bajar();
    try {
      final r = await Api.instancia.chat(mensaje: texto, imagen: imagen,
          historial: historial.length > 6 ? historial.sublist(historial.length - 6) : historial);
      Uint8List? anotada;
      final b64 = r['imagen_anotada'] as String?;
      if (b64 != null && b64.contains(',')) anotada = base64Decode(b64.split(',').last);
      _mensajes.add(_Mensaje(false, (r['respuesta'] ?? '').toString(),
          imagen: anotada,
          estadoCodigo: r['estado_codigo'] as int?,
          sugerencias: ((r['sugerencias'] ?? []) as List).map((e) => e.toString()).toList(),
          fuente: r['fuente']?.toString()));
    } on ApiError catch (e) {
      _mensajes.add(_Mensaje(false, '⚠️ ${e.mensaje}'));
    } catch (e) {
      _mensajes.add(_Mensaje(false, '⚠️ Sin conexión con el servidor: $e'));
    } finally {
      if (mounted) setState(() => _enviando = false);
      _bajar();
    }
  }

  Future<void> _foto(ImageSource origen) async {
    final x = await _picker.pickImage(source: origen, maxWidth: 1600, imageQuality: 85);
    if (x == null) return;
    await _enviar(texto: _texto.text, imagen: await x.readAsBytes());
  }

  void _bajar() => WidgetsBinding.instance.addPostFrameCallback((_) {
        if (_scroll.hasClients) _scroll.animateTo(_scroll.position.maxScrollExtent, duration: const Duration(milliseconds: 250), curve: Curves.easeOut);
      });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Modo Consulta · Agente')),
      body: Column(children: [
        Expanded(
          child: ListView.builder(
            controller: _scroll,
            padding: const EdgeInsets.all(12),
            itemCount: _mensajes.length + (_enviando ? 1 : 0),
            itemBuilder: (context, i) {
              if (i == _mensajes.length) {
                return const Align(alignment: Alignment.centerLeft, child: Padding(padding: EdgeInsets.all(8), child: CircularProgressIndicator()));
              }
              return _burbuja(_mensajes[i]);
            },
          ),
        ),
        SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(8, 4, 8, 8),
            child: Row(children: [
              IconButton(onPressed: _enviando ? null : () => _foto(ImageSource.camera), icon: const Icon(Icons.photo_camera)),
              IconButton(onPressed: _enviando ? null : () => _foto(ImageSource.gallery), icon: const Icon(Icons.photo_library)),
              Expanded(
                child: TextField(
                  controller: _texto,
                  textCapitalization: TextCapitalization.characters,
                  decoration: const InputDecoration(hintText: '¿Pasó la 2481-KLM?', border: OutlineInputBorder(), isDense: true),
                  onSubmitted: (v) => _enviar(texto: v),
                ),
              ),
              IconButton.filled(onPressed: _enviando ? null : () => _enviar(texto: _texto.text), icon: const Icon(Icons.send)),
            ]),
          ),
        ),
      ]),
    );
  }

  Widget _burbuja(_Mensaje m) {
    final tema = Theme.of(context);
    final estado = _estados[m.estadoCodigo];
    return Align(
      alignment: m.deUsuario ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.82),
        margin: const EdgeInsets.symmetric(vertical: 4),
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: m.deUsuario ? tema.colorScheme.primary : tema.colorScheme.surfaceContainerHighest,
          borderRadius: BorderRadius.circular(14),
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          if (estado != null)
            Container(
              margin: const EdgeInsets.only(bottom: 6),
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
              decoration: BoxDecoration(color: estado.$1, borderRadius: BorderRadius.circular(6)),
              child: Text(estado.$2, style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.bold)),
            ),
          Text(m.texto, style: TextStyle(color: m.deUsuario ? Colors.white : null)),
          if (m.imagen != null)
            Padding(padding: const EdgeInsets.only(top: 6), child: ClipRRect(borderRadius: BorderRadius.circular(8), child: Image.memory(m.imagen!))),
          if (m.sugerencias.isNotEmpty)
            Wrap(spacing: 6, children: [
              for (final s in m.sugerencias) ActionChip(label: Text(s), onPressed: () => _enviar(texto: '¿Pasó la placa $s?')),
            ]),
          if (m.fuente != null) Text(m.fuente!, style: const TextStyle(fontSize: 10, color: Colors.black45)),
        ]),
      ),
    );
  }
}
