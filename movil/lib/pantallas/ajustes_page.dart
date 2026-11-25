import 'package:flutter/material.dart';

import '../config.dart';
import '../servicios/api.dart';
import '../servicios/sesion.dart';

/// Ajustes rápidos: URL HTTPS del subdominio editable sin recompilar + verificación + sesión.
class AjustesPage extends StatefulWidget {
  const AjustesPage({super.key, this.embebida = false});
  final bool embebida;

  @override
  State<AjustesPage> createState() => _AjustesPageState();
}

class _AjustesPageState extends State<AjustesPage> {
  late final _url = TextEditingController(text: Api.instancia.baseUrl);
  String? _resultado;
  bool _probando = false;

  Future<void> _guardarYProbar() async {
    final u = _url.text.trim();
    if (!u.startsWith('https://')) {
      setState(() => _resultado = '⚠️ Use HTTPS (subdominio en la nube, nunca localhost).');
      return;
    }
    setState(() => _probando = true);
    await Api.instancia.cambiarBaseUrl(u);
    final ok = await Api.instancia.probarConexion();
    if (!mounted) return;
    setState(() {
      _probando = false;
      _resultado = ok ? '✅ Servidor en línea' : '❌ No responde ${Api.instancia.baseUrl}/up';
    });
  }

  @override
  Widget build(BuildContext context) {
    final u = Sesion.instancia.usuario;
    return Scaffold(
      appBar: AppBar(title: const Text('Ajustes'), automaticallyImplyLeading: !widget.embebida),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        Text('Servidor (API_BASE_URL)', style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 8),
        TextField(
          controller: _url,
          keyboardType: TextInputType.url,
          decoration: const InputDecoration(border: OutlineInputBorder(), hintText: 'https://peaje.tu-dominio.com'),
        ),
        const SizedBox(height: 8),
        Row(children: [
          FilledButton.icon(
            onPressed: _probando ? null : _guardarYProbar,
            icon: _probando ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.wifi_tethering),
            label: const Text('Guardar y verificar'),
          ),
          const SizedBox(width: 8),
          TextButton(onPressed: () => _url.text = Config.apiBaseUrl, child: const Text('Valor de compilación')),
        ]),
        if (_resultado != null) Padding(padding: const EdgeInsets.only(top: 8), child: Text(_resultado!)),
        const Divider(height: 32),
        if (u != null) ...[
          ListTile(
            leading: const Icon(Icons.verified_user),
            title: Text((u['nombre'] ?? '').toString()),
            subtitle: Text('${u['email']} · rol: ${u['rol']}'),
          ),
          OutlinedButton.icon(
            onPressed: () async {
              await Sesion.instancia.cerrarSesion();
              if (context.mounted && !widget.embebida) Navigator.of(context).pop();
            },
            icon: const Icon(Icons.logout),
            label: const Text('Cerrar sesión'),
          ),
        ],
        const SizedBox(height: 24),
        Text('Modo Caseta envía 2–5 fotos por segundo al backend en la nube; nunca hay más de una petición en vuelo. '
            'Las fotos no se guardan en el teléfono.',
            style: Theme.of(context).textTheme.bodySmall),
      ]),
    );
  }
}
