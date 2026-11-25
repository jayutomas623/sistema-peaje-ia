"""Normalización de placas bolivianas: 3 o 4 dígitos seguidos de 3 letras (^\\d{3,4}[A-Z]{3}$).

Los lectores OCR confunden caracteres parecidos (O↔0, I↔1, B↔8, S↔5, Z↔2, G↔6).
Como el formato boliviano fija qué posiciones son dígitos y cuáles letras, se corrige
cada posición según su tipo esperado. Se conserva siempre el texto crudo original.
"""
from __future__ import annotations

import re

FORMATO_BOLIVIA = re.compile(r"^\d{3,4}[A-Z]{3}$")

# Letra leída donde debería haber un dígito
A_DIGITO = {"O": "0", "Q": "0", "D": "0", "U": "0", "I": "1", "L": "1", "T": "1", "J": "1",
            "Z": "2", "S": "5", "B": "8", "G": "6", "A": "4", "E": "3"}
# Dígito leído donde debería haber una letra
A_LETRA = {"0": "O", "1": "I", "8": "B", "5": "S", "2": "Z", "6": "G", "4": "A", "7": "T", "3": "E"}


def limpiar(texto: str | None) -> str:
    """Mayúsculas y solo [A-Z0-9] (quita guiones, espacios, '_' de relleno, etc.)."""
    return re.sub(r"[^A-Z0-9]", "", (texto or "").upper())


def _corregir(candidato: str, n_digitos: int) -> tuple[str, int] | None:
    """Corrige un candidato de longitud n_digitos+3. Devuelve (texto, n_correcciones)."""
    if len(candidato) != n_digitos + 3:
        return None
    salida, cambios = [], 0
    for i, c in enumerate(candidato):
        if i < n_digitos:
            if c.isdigit():
                salida.append(c)
            elif c in A_DIGITO:
                salida.append(A_DIGITO[c]); cambios += 1
            else:
                return None
        else:
            if c.isalpha():
                salida.append(c)
            elif c in A_LETRA:
                salida.append(A_LETRA[c]); cambios += 1
            else:
                return None
    return "".join(salida), cambios


def normalizar_placa(texto_crudo: str | None) -> tuple[str, bool]:
    """Devuelve (placa_normalizada, cumple_formato_bolivia)."""
    t = limpiar(texto_crudo)
    if not t:
        return "", False
    if FORMATO_BOLIVIA.match(t):
        return t, True
    mejores: list[tuple[float, int, str, int]] = []  # (costo, -longitud, texto, cambios)
    # Ventanas de 7 y 6 caracteres (descarta leyendas como "BOLIVIA" pegadas al texto).
    # Costo = correcciones + 1.5 por cada carácter descartado: se prefiere usar todo el texto.
    for largo, n_dig in ((7, 4), (6, 3)):
        for ini in range(0, max(1, len(t) - largo + 1)):
            r = _corregir(t[ini:ini + largo], n_dig)
            if r and FORMATO_BOLIVIA.match(r[0]):
                descartados = max(0, len(t) - largo)
                mejores.append((r[1] + 1.5 * descartados, -largo, r[0], r[1]))
    if mejores:
        mejores.sort()
        _, _, texto, cambios = mejores[0]
        # Más de 2 correcciones es poco confiable: se reporta pero como formato dudoso
        return texto, cambios <= 2
    return t, False
