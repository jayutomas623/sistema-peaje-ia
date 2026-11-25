@extends('layouts.publico')
@section('titulo', 'Acceso denegado')
@section('contenido')
<div class="card tarjeta shadow-lg w-100 border-danger">
    <div class="card-body p-4 p-md-5 text-center">
        <div class="display-3 text-danger mb-2"><i class="bi bi-shield-x"></i></div>
        <h1 class="h3 fw-bold text-danger">Acceso denegado</h1>
        <p class="mb-1">Esta cuenta de Google <strong>no está autorizada</strong> para usar el sistema.</p>
        @if ($email)
            <p class="mb-3"><code class="fs-6">{{ $email }}</code></p>
        @endif
        <p class="text-secondary small">Solicite al administrador que agregue su correo a la lista blanca de usuarios.</p>
        <a href="{{ route('login') }}" class="btn btn-outline-secondary mt-2"><i class="bi bi-arrow-left"></i> Probar con otra cuenta</a>
    </div>
</div>
@endsection
