import 'package:flutter/material.dart';

import 'pantallas/home_page.dart';
import 'pantallas/login_page.dart';
import 'servicios/sesion.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const PeajeApp());
}

class PeajeApp extends StatelessWidget {
  const PeajeApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Peaje IA',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF1D4ED8)),
        useMaterial3: true,
      ),
      home: const Arranque(),
    );
  }
}

/// Restaura la sesión guardada y decide entre Login y Home.
class Arranque extends StatefulWidget {
  const Arranque({super.key});

  @override
  State<Arranque> createState() => _ArranqueState();
}

class _ArranqueState extends State<Arranque> {
  late final Future<void> _carga = Sesion.instancia.restaurar();

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<void>(
      future: _carga,
      builder: (context, snap) {
        if (snap.connectionState != ConnectionState.done) {
          return const Scaffold(body: Center(child: CircularProgressIndicator()));
        }
        return ListenableBuilder(
          listenable: Sesion.instancia,
          builder: (context, _) => Sesion.instancia.autenticado ? const HomePage() : const LoginPage(),
        );
      },
    );
  }
}
