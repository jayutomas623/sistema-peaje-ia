<?php

namespace App\Services\Agente\Proveedores;

interface ProveedorLlm
{
    /**
     * Ejecuta un diálogo con function calling.
     *
     * @param  string  $sistema  instrucciones del sistema
     * @param  array<int, array{rol: string, texto: string}>  $mensajes  historial + mensaje actual
     * @param  array  $herramientas  declaraciones JSON-Schema
     * @param  callable(string, array): array  $ejecutor  ejecuta una herramienta y devuelve su resultado
     * @return string respuesta final en texto
     */
    public function responder(string $sistema, array $mensajes, array $herramientas, callable $ejecutor): string;

    public function nombre(): string;
}
