@extends('layouts.app')
@section('titulo', 'Estado del sistema')
@section('contenido')
<div class="row g-3">
    <div class="col-xl-7">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white d-flex align-items-center"><strong><i class="bi bi-gpu-card"></i> Servidor de inferencia</strong>
                <span class="ms-2 badge" id="inf-badge">consultando…</span>
                <button class="btn btn-sm btn-outline-secondary ms-auto" id="btn-ping"><i class="bi bi-arrow-repeat"></i> Ping</button></div>
            <div class="card-body">
                <p class="mb-2 small">URL: <code id="inf-url">{{ $inferenceUrl ?: 'no configurada' }}</code> · latencia: <strong id="inf-lat">—</strong></p>
                <div class="row g-2 small" id="inf-detalle"></div>
                <div class="alert alert-warning small mt-2 d-none" id="inf-adv"></div>
                <div class="alert alert-danger small mt-2 d-none" id="inf-err"></div>
            </div>
        </div>

        @if (auth()->user()->esAdmin())
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong><i class="bi bi-sliders"></i> Configuración (sin redeploy)</strong></div>
            <form method="POST" action="{{ route('estado.guardar') }}" class="card-body">
                @csrf
                <label class="form-label">INFERENCE_URL</label>
                <input name="inference_url" value="{{ old('inference_url', $inferenceUrl) }}" class="form-control mb-1" placeholder="https://POD_ID-8000.proxy.runpod.net" required>
                <div class="form-text mb-2">RunPod: <code>https://&lt;POD_ID&gt;-8000.proxy.runpod.net</code> · Colab: URL <code>trycloudflare.com</code> del notebook.</div>
                <label class="form-label">INFERENCE_TOKEN (X-API-Token)</label>
                <input name="inference_token" type="password" class="form-control mb-1" autocomplete="new-password"
                       placeholder="{{ $tokenConfigurado ? '•••••••• (configurado; deje vacío para no cambiarlo)' : 'pegue el API_TOKEN del servidor' }}">
                <div class="form-text mb-2">Se guarda cifrado con APP_KEY.</div>
                <label class="form-label">Categoría que se cobra</label>
                <select name="prioridad_categoria" class="form-select mb-3">
                    <option value="ia" @selected($prioridad === 'ia')>La detectada por la IA (M2), si su confianza ≥ 0.5</option>
                    <option value="registro" @selected($prioridad === 'registro')>La registrada del vehículo (si existe)</option>
                </select>
                <button class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button>
            </form>
        </div>
        @endif
    </div>

    <div class="col-xl-5">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white"><strong><i class="bi bi-cpu"></i> Dispositivo ESP32 (alertas)</strong> <span class="badge ms-1" id="esp-badge">—</span></div>
            <div class="card-body small" id="esp-detalle">—</div>
        </div>
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white"><strong><i class="bi bi-broadcast"></i> Tiempo real y agente</strong></div>
            <div class="card-body small" id="otros-detalle">—</div>
        </div>
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong><i class="bi bi-hdd-network"></i> Arquitectura en la nube</strong></div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item">Roboflow → datasets M2/M3/M4</li>
                <li class="list-group-item">Google Colab (T4) → entrenamiento de pesos</li>
                <li class="list-group-item">RunPod (GPU) → FastAPI <code>/inferir</code> puerto 8000</li>
                <li class="list-group-item">Este subdominio HTTPS → Laravel + API + BD + agente</li>
                <li class="list-group-item">Pusher → panel en tiempo real · ESP32 → HTTPS</li>
            </ul>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
(function () {
    const E = PeajeRT.esc;
    const badge = (el, ok, si, no) => { el.className = 'badge ms-1 ' + (ok ? 'text-bg-success' : 'text-bg-danger'); el.textContent = ok ? si : no; };
    async function ping() {
        const r = await fetch(@json(route('estado.health')), { headers: { Accept: 'application/json' } });
        const d = await r.json(), i = d.inferencia, h = i.datos || {};
        badge(document.getElementById('inf-badge'), i.ok, 'en línea', 'sin conexión');
        document.getElementById('inf-url').textContent = i.url || 'no configurada';
        document.getElementById('inf-lat').textContent = i.latencia_ms + ' ms';
        const err = document.getElementById('inf-err'); err.classList.toggle('d-none', !i.error); err.textContent = i.error || '';
        const adv = document.getElementById('inf-adv'); adv.classList.toggle('d-none', !h.advertencia); adv.textContent = h.advertencia || '';
        const m = h.modelos_cargados || {}, v = h.versiones || {}, g = h.gpu || {};
        document.getElementById('inf-detalle').innerHTML = i.ok ? `
            <div class="col-md-6"><strong>GPU:</strong> ${g.cuda_disponible ? '✅ ' + E(g.nombre) : '⚠️ CPU'}</div>
            <div class="col-md-6"><strong>Calentamiento:</strong> ${E(h.latencia_calentamiento_ms)} ms · <strong>inferencias:</strong> ${E(h.inferencias_atendidas)}</div>
            <div class="col-12"><strong>M1 placas:</strong> ${E(m.m1_placas)}</div>
            <div class="col-12"><strong>M2 vehículos:</strong> ${E(m.m2_vehiculos)}</div>
            <div class="col-12"><strong>M3 ejes:</strong> ${E(m.m3_ejes)}</div>
            <div class="col-12"><strong>M4 pasajeros:</strong> ${E(m.m4_pasajeros)}</div>
            <div class="col-12 text-secondary">Python ${E(v.python)} · torch ${E(v.torch)} · ultralytics ${E(v.ultralytics)} · onnxruntime ${E(v.onnxruntime)} · ${E(h.sistema)}</div>` : '';
        const s = d.esp32;
        badge(document.getElementById('esp-badge'), s.conectado, 'conectado', 'desconectado');
        const ev = s.ultimo_evento || {};
        document.getElementById('esp-detalle').innerHTML = `Último contacto: <strong>${E(s.ultimo_contacto || 'nunca')}</strong>
            ${s.rssi ? ' · RSSI ' + E(s.rssi) + ' dBm' : ''} ${s.fw ? ' · fw ' + E(s.fw) : ''}<br>
            Última señal: <span class="badge text-bg-${({ ok: 'success', alerta: 'danger', inferencia: 'primary' })[ev.tipo_senal] || 'secondary'}">${E(ev.tipo_senal || 'idle')}</span>
            ${ev.placa ? PeajeRT.placa(ev.placa) : ''} ${ev.monto ? PeajeRT.bs(ev.monto) : ''}`;
        document.getElementById('otros-detalle').innerHTML = `Broadcast: <strong>${E(d.tiempo_real.driver)}</strong> ${d.tiempo_real.configurado ? '✅' : '⚠️ sin credenciales (se usa sondeo)'}<br>
            Agente LLM: <strong>${E(d.agente.proveedor)}</strong> ${d.agente.configurado ? '✅' : '⚠️ sin API key → modo determinista'}<br>
            Servidor: PHP ${E(d.servidor.php)} · Laravel ${E(d.servidor.laravel)} · BD ${E(d.servidor.bd)} · ${E(d.servidor.hora)}`;
    }
    document.getElementById('btn-ping').addEventListener('click', ping);
    ping(); setInterval(ping, 10000);
})();
</script>
@endpush
