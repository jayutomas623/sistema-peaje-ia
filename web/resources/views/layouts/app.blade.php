<!doctype html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Panel') · Peaje IA</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🚧</text></svg>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/peaje.css') }}?v=2" rel="stylesheet">
    @stack('head')
</head>
<body class="bg-body-tertiary">
@php $u = auth()->user(); @endphp
<div class="d-flex min-vh-100">
    <nav class="sidebar d-flex flex-column flex-shrink-0 p-3 text-bg-dark" id="sidebar">
        <a href="{{ route('panel') }}" class="d-flex align-items-center mb-3 text-white text-decoration-none">
            <span class="fs-4 me-2">🚧</span><span class="fs-5 fw-semibold">Peaje IA</span>
        </a>
        <span class="badge text-bg-secondary mb-3 text-wrap">Sistema Inteligente de Control de Peaje</span>
        <ul class="nav nav-pills flex-column mb-auto gap-1">
            @php
            $items = [
                ['panel', 'speedometer2', 'Panel en vivo', true],
                ['pasos.index', 'car-front', 'Pasos', true],
                ['alertas.index', 'exclamation-triangle', 'Alertas', true],
                ['vehiculos.index', 'truck', 'Vehículos', true],
                ['tarifas.index', 'cash-coin', 'Tarifas', $u->esAdmin()],
                ['casetas.index', 'signpost-split', 'Casetas', $u->esAdmin()],
                ['usuarios.index', 'person-check', 'Usuarios (lista blanca)', $u->esAdmin()],
                ['chat', 'chat-dots', 'Agente conversacional', true],
                ['estado', 'activity', 'Estado del sistema', true],
            ];
            @endphp
            @foreach ($items as [$ruta, $icono, $texto, $visible])
                @if ($visible)
                    <li class="nav-item">
                        <a href="{{ route($ruta) }}" class="nav-link text-white {{ request()->routeIs(explode('.', $ruta)[0].'*') ? 'active' : '' }}">
                            <i class="bi bi-{{ $icono }} me-2"></i>{{ $texto }}
                            @if ($ruta === 'alertas.index')
                                <span class="badge rounded-pill text-bg-danger ms-1" id="badge-alertas">{{ \App\Models\Alerta::where('atendida', false)->count() }}</span>
                            @endif
                        </a>
                    </li>
                @endif
            @endforeach
        </ul>
        <hr>
        <div class="d-flex align-items-center gap-2">
            @if ($u->avatar)
                <img src="{{ $u->avatar }}" alt="" width="36" height="36" class="rounded-circle" referrerpolicy="no-referrer">
            @else
                <i class="bi bi-person-circle fs-3"></i>
            @endif
            <div class="small lh-sm overflow-hidden">
                <div class="fw-semibold text-truncate" style="max-width: 150px">{{ $u->nombre }}</div>
                <span class="badge {{ $u->esAdmin() ? 'text-bg-warning' : 'text-bg-info' }}">{{ $u->rol }}</span>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="ms-auto">@csrf
                <button class="btn btn-sm btn-outline-light" title="Cerrar sesión"><i class="bi bi-box-arrow-right"></i></button>
            </form>
        </div>
    </nav>

    <main class="flex-grow-1 p-3 p-lg-4 overflow-auto">
        <div class="d-flex align-items-center mb-3 gap-2">
            <button class="btn btn-outline-secondary d-lg-none" onclick="document.getElementById('sidebar').classList.toggle('abierta')"><i class="bi bi-list"></i></button>
            <h1 class="h4 mb-0">@yield('titulo', 'Panel')</h1>
            <span class="ms-auto small text-secondary" id="estado-rt" title="Conexión en tiempo real"><i class="bi bi-circle-fill text-secondary"></i> tiempo real</span>
        </div>
        @if (session('ok'))
            <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-1"></i>{{ session('ok') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-x-circle me-1"></i>{{ session('error') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif
        @yield('contenido')
    </main>
</div>

{{-- Modal de evidencia reutilizable --}}
<div class="modal fade" id="modalEvidencia" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="modalEvidenciaTitulo">Evidencia</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body text-center"><img id="modalEvidenciaImg" src="" alt="Evidencia" class="img-fluid rounded"></div>
        </div>
    </div>
</div>

@unless (request()->routeIs('chat'))
    @include('partials.chat-burbuja')
@endunless

<script>
    window.PEAJE = {
        csrf: @json(csrf_token()),
        broadcaster: @json(config('broadcasting.default')),
        pusherKey: @json(config('broadcasting.connections.pusher.key')),
        pusherCluster: @json(config('broadcasting.connections.pusher.options.cluster')),
        reverbKey: @json(config('broadcasting.connections.reverb.key')),
        reverbHost: @json(config('peaje.reverb_publico.host')),
        reverbPort: @json(config('peaje.reverb_publico.port')),
        feedUrl: @json(route('panel.feed')),
        chatUrl: @json(route('chat.enviar')),
    };
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
<script src="{{ asset('js/peaje.js') }}?v=1"></script>
<script src="{{ asset('js/chat.js') }}?v=1"></script>
@stack('scripts')
</body>
</html>
