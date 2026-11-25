@extends('layouts.app')
@section('titulo', 'Agente conversacional')
@section('contenido')
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow-sm d-flex flex-column" style="height: calc(100vh - 160px); min-height: 480px" data-chat>
            <div class="card-header bg-white d-flex align-items-center"><i class="bi bi-robot me-2 text-primary"></i><strong>Agente Peaje</strong>
                <span class="small text-secondary ms-2">proveedor: {{ config('peaje.agente.proveedor') }} · respaldo determinista</span>
                <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" data-limpiar><i class="bi bi-arrow-counterclockwise"></i> Nueva conversación</button></div>
            <div class="chat-mensajes"></div>
            @include('partials.chat-form')
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm"><div class="card-body small">
            <h2 class="h6">¿Qué puedo preguntar?</h2>
            <ul class="mb-3">
                <li>“¿Pasó la placa 1151DKL?” → <span class="badge text-bg-success">Registrada con paso</span></li>
                <li>“Consulta 5592-UTF” → <span class="badge text-bg-primary">Registrada sin paso</span></li>
                <li>“¿Y la 1917YBP?” → <span class="badge text-bg-danger">No registrada · evasión</span></li>
                <li>“Busca 2481KLN” → <span class="badge text-bg-secondary">No existe</span> + ¿Quisiste decir…?</li>
                <li>“Resumen de hoy por caseta”, “alertas activas”, “tarifas”.</li>
                <li>📷 Adjunta una foto: el agente lee la placa con M1 en RunPod.</li>
            </ul>
            <p class="text-secondary mb-0"><i class="bi bi-shield-check"></i> El agente solo responde con datos de la base de datos
                (function calling); si el LLM no está disponible usa un modo determinista con la misma información.</p>
        </div></div>
    </div>
</div>
@endsection
