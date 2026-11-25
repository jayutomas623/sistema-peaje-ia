import 'package:flutter/material.dart';

import '../servicios/api.dart';
import '../servicios/sesion.dart';
import 'acceso_denegado_page.dart';
import 'ajustes_page.dart';

/// Login EXCLUSIVO con Google. No existen usuarios ni contraseñas propias.
class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  bool _cargando = false;
  String? _error;

  Future<void> _login() async {
    setState(() {
      _cargando = true;
      _error = null;
    });
    final r = await Sesion.instancia.loginConGoogle();
    if (!mounted) return;
    setState(() => _cargando = false);
    switch (r) {
      case ResultadoLogin.denegado:
        await Navigator.of(context).push(MaterialPageRoute(
          builder: (_) => AccesoDenegadoPage(email: Sesion.instancia.emailDenegado),
        ));
      case ResultadoLogin.error:
        setState(() => _error = Sesion.instancia.ultimoError);
      case ResultadoLogin.ok:
      case ResultadoLogin.cancelado:
        break;
    }
  }

  @override
  Widget build(BuildContext context) {
    final tema = Theme.of(context);
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(colors: [Color(0xFF0F172A), Color(0xFF1E3A8A)], begin: Alignment.topLeft, end: Alignment.bottomRight),
        ),
        child: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: Card(
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                child: Padding(
                  padding: const EdgeInsets.all(28),
                  child: Column(mainAxisSize: MainAxisSize.min, children: [
                    const Text('🚧', style: TextStyle(fontSize: 56)),
                    const SizedBox(height: 8),
                    Text('Sistema Inteligente de\nControl de Peaje',
                        textAlign: TextAlign.center, style: tema.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold)),
                    const SizedBox(height: 4),
                    Text('Placas bolivianas · categoría · ejes · pasajeros',
                        textAlign: TextAlign.center, style: tema.textTheme.bodySmall),
                    const SizedBox(height: 28),
                    SizedBox(
                      width: double.infinity,
                      child: FilledButton.icon(
                        onPressed: _cargando ? null : _login,
                        icon: _cargando
                            ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                            : const Icon(Icons.account_circle),
                        label: const Padding(
                          padding: EdgeInsets.symmetric(vertical: 12),
                          child: Text('Iniciar sesión con Google', style: TextStyle(fontSize: 16)),
                        ),
                      ),
                    ),
                    if (_error != null) ...[
                      const SizedBox(height: 16),
                      Text(_error!, style: TextStyle(color: tema.colorScheme.error), textAlign: TextAlign.center),
                    ],
                    const SizedBox(height: 20),
                    Text('Solo cuentas de Google autorizadas (lista blanca).',
                        style: tema.textTheme.bodySmall, textAlign: TextAlign.center),
                    TextButton.icon(
                      onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const AjustesPage())),
                      icon: const Icon(Icons.settings, size: 18),
                      label: Text('Servidor: ${Api.instancia.baseUrl.replaceFirst('https://', '')}', overflow: TextOverflow.ellipsis),
                    ),
                  ]),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
