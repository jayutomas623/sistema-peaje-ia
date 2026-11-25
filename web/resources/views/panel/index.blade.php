@extends('layouts.app')
@section('titulo', 'Panel en tiempo real')
@section('contenido')
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="card kpi shadow-sm h-100"><div class="card-body">
        <div class="text-secondary small"><i class="bi bi-car-front"></i> Pasos hoy</div>
        <div class="valor" id="kpi-pasos">{{ $contadores['pasos_hoy'] }}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="card kpi verde shadow-sm h-100"><div class="card-body">
        <div class="text-secondary small"><i class="bi bi-cash-coin"></i> Recaudación hoy</div>
        <div class="valor" id="kpi-recaudacion">Bs {{ number_format($contadores['recaudacion_hoy'], 2) }}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="card kpi rojo shadow-sm h-100"><div class="card-body">
        <div class="text-secondary small"><i class="bi bi-exclamation-triangle"></i> Alertas activas</div>
        <div class="valor text-danger" id="kpi-alertas">{{ $contadores['alertas_activas'] }}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="card kpi violeta shadow-sm h-100"><div class="card-body">
        <div class="text-secondary small"><i class="bi bi-people"></i> Pasajeros detectados hoy</div>
        <div class="valor" id="kpi-pasajeros">{{ $contadores['pasajeros_hoy'] }}</div></div></div></div>
</div>

<div class="row g-3">
    <div class="col-xxl-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex align-items-center">
                <strong><i class="bi bi-broadcast text-danger"></i> Pasos en vivo</strong>
                <span class="small text-secondary ms-2">se actualiza sin recargar</span>
                <a href="{{ route('pasos.index') }}" class="btn btn-sm btn-link ms-auto">Historial completo</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 tabla-compacta">
                    <thead class="table-light small"><tr>
                        <th>Evidencia</th><th>Placa</th><th>Categoría</th><th class="text-center">Ejes</th>
                        <th class="text-center">Pasajeros</th><th class="text-end">Monto</th><th>Estado</th><th>Caseta / hora</th>
                    </tr></thead>
                    <tbody id="tabla-pasos">
                    @forelse ($pasos as $p)
                        @include('panel._fila', ['p' => $p])
                    @empty
                        <tr id="sin-pasos"><td colspan="8" class="text-center text-secondary py-4">Aún no hay pasos registrados.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xxl-4">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white d-flex align-items-center">
                <strong><i class="bi bi-bell text-danger"></i> Alertas activas</strong>
                <div class="form-check form-switch ms-auto small mb-0" title="Sonido al llegar una alerta">
                    <input class="form-check-input" type="checkbox" id="sonido">
                    <label class="form-check-label" for="sonido"><i class="bi bi-volume-up"></i></label>
                </div>
            </div>
            <ul class="list-group list-group-flush" id="lista-alertas">
                @forelse ($alertas as $a)
                    @include('panel._alerta', ['a' => $a])
                @empty
                    <li class="list-group-item text-secondary small" id="sin-alertas">Sin alertas pendientes 🎉</li>
                @endforelse
            </ul>
            <div class="card-footer bg-white text-end"><a href="{{ route('alertas.index') }}" class="small">Ver todas</a></div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong><i class="bi bi-camera"></i> Registrar paso con una foto</strong></div>
            <div class="card-body">
                <form method="POST" action="{{ route('pasos.store') }}" enctype="multipart/form-data" id="form-paso">
                    @csrf
                    <select name="caseta_id" class="form-select mb-2" required>
                        @foreach ($casetas as $c)
                            <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                        @endforeach
                    </select>
                    <input type="file" name="imagen" accept="image/*" capture="environment" class="form-control mb-2" required>
                    <button class="btn btn-primary w-100" id="btn-paso"><i class="bi bi-cpu"></i> Inferir y registrar</button>
                    <div class="form-text">Envía la imagen a <code>INFERENCE_URL/inferir</code> (RunPod/Colab) y registra el paso.</div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const R = PeajeRT;
    const sonido = document.getElementById('sonido');
    sonido.checked = localStorage.getItem('peaje_sonido') === '1';
    sonido.addEventListener('change', () => { localStorage.setItem('peaje_sonido', sonido.checked ? '1' : '0'); if (sonido.checked) R.beep(1); });
    document.getElementById('form-paso').addEventListener('submit', () => {
        const b = document.getElementById('btn-paso'); b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Procesando…';
    });

    function fila(p) {
        const ev = p.evidencia_url ? `<img src="${p.evidencia_url}" class="evidencia-mini" data-evidencia="${p.evidencia_url}" data-titulo="Paso #${p.id} · ${R.esc(p.placa || 'ilegible')}" alt="evidencia">`
            : '<div class="evidencia-mini d-flex align-items-center justify-content-center text-secondary"><i class="bi bi-image"></i></div>';
        return `<tr class="fila-nueva" data-id="${p.id}">
            <td>${ev}</td>
            <td>${R.placa(p.placa)}${p.propietario ? `<div class="small text-secondary text-truncate" style="max-width:160px">${R.esc(p.propietario)}</div>` : ''}</td>
            <td class="text-capitalize">${R.esc(p.categoria || '—')}</td>
            <td class="text-center">${p.ejes ?? '—'}</td>
            <td class="text-center">${p.pasajeros ?? '—'}</td>
            <td class="text-end fw-semibold">${R.bs(p.monto)}</td>
            <td>${R.estado(p.estado, p.estado_texto)}</td>
            <td class="small">${R.esc(p.caseta)}<br><span class="text-secondary">${R.esc(p.hora)}</span></td></tr>`;
    }

    R.on('paso.registrado', d => {
        const tb = document.getElementById('tabla-pasos');
        if (tb.querySelector(`tr[data-id="${d.paso.id}"]`)) return;
        document.getElementById('sin-pasos')?.remove();
        tb.insertAdjacentHTML('afterbegin', fila(d.paso));
        while (tb.rows.length > 20) tb.deleteRow(-1);
    });
    R.on('contadores', c => {
        if (!c) return;
        document.getElementById('kpi-pasos').textContent = c.pasos_hoy;
        document.getElementById('kpi-recaudacion').textContent = R.bs(c.recaudacion_hoy);
        document.getElementById('kpi-alertas').textContent = c.alertas_activas;
        document.getElementById('kpi-pasajeros').textContent = c.pasajeros_hoy;
    });
    R.on('alerta.creada', d => {
        const a = d.alerta, ul = document.getElementById('lista-alertas');
        if (ul.querySelector(`li[data-id="${a.id}"]`)) return;
        document.getElementById('sin-alertas')?.remove();
        ul.insertAdjacentHTML('afterbegin', `<li class="list-group-item fila-nueva" data-id="${a.id}">
            <div class="d-flex"><strong class="text-danger small">${R.esc(a.tipo_texto)}</strong><span class="ms-auto small text-secondary">${R.esc(a.hora)}</span></div>
            <div class="small">${R.esc(a.mensaje)}</div><div class="small text-secondary">${R.esc(a.caseta || '')}</div></li>`);
        while (ul.children.length > 6) ul.lastElementChild.remove();
    });
    R.on('alerta.atendida', d => document.querySelector(`#lista-alertas li[data-id="${d.alertaId}"]`)?.remove());
})();
</script>
@endpush
