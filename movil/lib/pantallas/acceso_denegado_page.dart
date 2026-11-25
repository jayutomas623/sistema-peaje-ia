import 'package:flutter/material.dart';

/// Pantalla mostrada cuando el backend responde HTTP 403 (cuenta fuera de la lista blanca).
class AccesoDenegadoPage extends StatelessWidget {
  const AccesoDenegadoPage({super.key, this.email});
  final String? email;

  @override
  Widget build(BuildContext context) {
    final rojo = Theme.of(context).colorScheme.error;
    return Scaffold(
      backgroundColor: const Color(0xFF450A0A),
      body: SafeArea(
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Card(
              child: Padding(
                padding: const EdgeInsets.all(28),
                child: Column(mainAxisSize: MainAxisSize.min, children: [
                  Icon(Icons.gpp_bad, size: 84, color: rojo),
                  const SizedBox(height: 12),
                  Text('Acceso denegado',
                      style: Theme.of(context).textTheme.headlineSmall?.copyWith(color: rojo, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 12),
                  const Text('Esta cuenta de Google no está autorizada para usar el sistema.', textAlign: TextAlign.center),
                  if (email != null) ...[
                    const SizedBox(height: 8),
                    SelectableText(email!, style: const TextStyle(fontWeight: FontWeight.bold)),
                  ],
                  const SizedBox(height: 12),
                  const Text('Pida al administrador que agregue su correo a la lista blanca.',
                      textAlign: TextAlign.center, style: TextStyle(fontSize: 13)),
                  const SizedBox(height: 20),
                  OutlinedButton.icon(
                    onPressed: () => Navigator.of(context).pop(),
                    icon: const Icon(Icons.arrow_back),
                    label: const Text('Probar con otra cuenta'),
                  ),
                ]),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
