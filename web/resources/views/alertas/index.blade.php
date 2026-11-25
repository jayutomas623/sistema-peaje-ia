@extends('layouts.app')
@section('titulo', 'Alertas')
@section('contenido')
<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    <div class="btn-group">
        @foreach (['activas' => 'Activas', 'atendidas' => 'Atendidas', 'todas' => 'Todas'] as $k => $t)
            <a href="{{ route('alertas.index', ['ver' => $k]) }}" class="btn btn-sm {{ $ver === $k ? 'btn-dark' : 'btn-outline-dark' }}">{{ $t }}</a>
        @endforeach
    </div>
    <div class="form-check form-switch ms-auto">
        <input class="form-check-input" type="checkbox" id="sonido">
        <label class="form-check-label" for="sonido"><i class="bi bi-volume-up"></i> Alerta sonora</label>
    </div>
</div>
<div class="alert alert-info small py-2 d-none" id="aviso-nuevas"><i class="bi bi-bell-fill"></i> Llegaron <span id="n-nuevas">0</span> alertas nuevas.
    <a href="{{ route('alertas.index') }}">Actualizar lista</a></div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light small"><tr><th>Fecha</th><th>Tipo</th><th>Placa</th><th>Mensaje</th><th>Caseta</th><th>Evidencia</th><th></th></tr></thead>
            <tbody id="tabla-alertas">
            @forelse ($alertas as $a)
                <tr data-id="{{ $a->id }}" class="{{ $a->atendida ? 'text-secondary' : '' }}">
                    <td class="small">{{ $a->created_at->format('d/m/Y H:i:s') }}</td>
                    <td><span class="badge {{ $a->severidad === 'alta' ? 'text-bg-danger' : 'text-bg-warning' }}">{{ \App\Models\Alerta::TIPOS[$a->tipo] }}</span></td>
                    <td>@if ($a->placa)<span class="placa">{{ $a->placa }}</span>@else — @endif</td>
                    <td class="small">{{ $a->mensaje }}</td>
                    <td class="small">{{ $a->caseta?->nombre }}</td>
                    <td>@if ($a->paso?->evidencia_url)<img src="{{ $a->paso->evidencia_url }}" class="evidencia-mini" loading="lazy" alt=""
                            data-evidencia="{{ $a->paso->evidencia_url }}" data-titulo="Alerta #{{ $a->id }}">@endif</td>
                    <td class="text-end">
                        @if ($a->atendida)
                            <span class="small"><i class="bi bi-check2-all text-success"></i> {{ $a->atendidaPor?->nombre }}<br>{{ $a->atendida_at?->format('d/m H:i') }}</span>
                        @else
                            <form method="POST" action="{{ route('alertas.atender', $a) }}">@csrf
                                <button class="btn btn-sm btn-success"><i class="bi bi-check2"></i> Marcar como atendida</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-secondary py-4">No hay alertas en esta vista.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">{{ $alertas->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
@push('scripts')
<script>
(function () {
    const s = document.getElementById('sonido');
    s.checked = localStorage.getItem('peaje_sonido') === '1';
    s.addEventListener('change', () => { localStorage.setItem('peaje_sonido', s.checked ? '1' : '0'); if (s.checked) PeajeRT.beep(1); });
    let n = 0;
    PeajeRT.on('alerta.creada', d => {
        const tb = document.getElementById('tabla-alertas');
        if (tb.querySelector(`tr[data-id="${d.alerta.id}"]`)) return;
        const a = d.alerta, E = PeajeRT.esc;
        tb.insertAdjacentHTML('afterbegin', `<tr class="fila-nueva" data-id="${a.id}"><td class="small">${E(a.hora)}</td>
            <td><span class="badge text-bg-danger">${E(a.tipo_texto)}</span></td><td>${a.placa ? PeajeRT.placa(a.placa) : '—'}</td>
            <td class="small">${E(a.mensaje)}</td><td class="small">${E(a.caseta || '')}</td>
            <td>${a.evidencia_url ? `<img src="${a.evidencia_url}" class="evidencia-mini" data-evidencia="${a.evidencia_url}" alt="">` : ''}</td>
            <td class="text-end"><button class="btn btn-sm btn-success" data-atender="${a.id}"><i class="bi bi-check2"></i> Marcar como atendida</button></td></tr>`);
        document.getElementById('aviso-nuevas').classList.remove('d-none');
        document.getElementById('n-nuevas').textContent = ++n;
    });
    document.addEventListener('click', async e => {
        const b = e.target.closest('[data-atender]');
        if (!b) return;
        b.disabled = true;
        const r = await fetch(`{{ url('alertas') }}/${b.dataset.atender}/atender`, { method: 'POST',
            headers: { 'X-CSRF-TOKEN': PEAJE.csrf, Accept: 'application/json' } });
        if (r.ok) b.closest('td').innerHTML = '<span class="small text-success"><i class="bi bi-check2-all"></i> Atendida</span>';
    });
})();
</script>
@endpush
