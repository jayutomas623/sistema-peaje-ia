import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:peaje_ia_movil/pantallas/acceso_denegado_page.dart';
import 'package:peaje_ia_movil/servicios/api.dart';

void main() {
  testWidgets('La pantalla de acceso denegado muestra el correo rechazado', (tester) async {
    await tester.pumpWidget(const MaterialApp(home: AccesoDenegadoPage(email: 'intruso@gmail.com')));
    expect(find.text('Acceso denegado'), findsOneWidget);
    expect(find.text('intruso@gmail.com'), findsOneWidget);
  });

  test('ApiError 403 se interpreta como acceso denegado', () {
    final e = ApiError(403, 'Acceso denegado: cuenta no autorizada');
    expect(e.accesoDenegado, isTrue);
    expect(e.noAutenticado, isFalse);
  });
}
