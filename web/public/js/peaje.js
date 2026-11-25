/* Tiempo real del panel: Laravel Echo (Pusher por defecto / Reverb) con respaldo por sondeo. */
(function () {
    const C = window.PEAJE || {};
    const oyentes = {};
    let conectado = false;
    let sondeo = null;

    function indicador(estado, texto) {
        const el = document.getElementById('estado-rt');
        if (!el) return;
        const color = { ok: 'text-success', sondeo: 'text-warning', off: 'text-secondary' }[estado];
        el.innerHTML = `<i class="bi bi-circle-fill ${color}"></i> ${texto}`;
    }

    function emitir(evento, datos) {
        (oyentes[evento] || []).forEach(cb => { try { cb(datos); } catch (e) { console.error(e); } });
    }

    function iniciarSondeo() {
        if (sondeo) return;
        indicador('sondeo', 'actualización cada 5 s');
        let ultimoPaso = null, ultimaAlerta = null;
        const tick = async () => {
            try {
                const r = await fetch(C.feedUrl, { headers: { Accept: 'application/json' } });
                if (!r.ok) return;
                const d = await r.json();
                emitir('contadores', d.contadores);
                const p = d.pasos && d.pasos[0];
                if (p && ultimoPaso !== null && p.id !== ultimoPaso) {
                    d.pasos.filter(x => x.id > ultimoPaso).reverse().forEach(x => emitir('paso.registrado', { paso: x, contadores: d.contadores }));
                }
                if (p) ultimoPaso = p.id; else if (ultimoPaso === null) ultimoPaso = 0;
                const a = d.alertas && d.alertas[0];
                if (a && ultimaAlerta !== null && a.id > ultimaAlerta) {
                    d.alertas.filter(x => x.id > ultimaAlerta).reverse().forEach(x => emitir('alerta.creada', { alerta: x }));
                }
                if (a) ultimaAlerta = Math.max(ultimaAlerta || 0, a.id); else if (ultimaAlerta === null) ultimaAlerta = 0;
                actualizarBadge(d.contadores.alertas_activas);
            } catch (e) { /* sin red */ }
        };
        tick();
        sondeo = setInterval(tick, 5000);
    }

    function iniciarEcho() {
        try {
            const csrf = { headers: { 'X-CSRF-TOKEN': C.csrf } };
            if (C.broadcaster === 'pusher' && C.pusherKey) {
                window.Echo = new Echo({ broadcaster: 'pusher', key: C.pusherKey, cluster: C.pusherCluster, forceTLS: true, auth: csrf });
            } else if (C.broadcaster === 'reverb' && C.reverbKey) {
                window.Echo = new Echo({ broadcaster: 'reverb', key: C.reverbKey, wsHost: C.reverbHost, wsPort: C.reverbPort,
                    wssPort: C.reverbPort, forceTLS: true, enabledTransports: ['ws', 'wss'], auth: csrf });
            } else {
                return iniciarSondeo();
            }
            const conn = window.Echo.connector.pusher.connection;
            conn.bind('connected', () => { conectado = true; indicador('ok', 'en vivo (' + C.broadcaster + ')'); if (sondeo) { clearInterval(sondeo); sondeo = null; } });
            conn.bind('unavailable', () => { conectado = false; iniciarSondeo(); });
            conn.bind('failed', () => { conectado = false; iniciarSondeo(); });
            window.Echo.private('peaje')
                .listen('.paso.registrado', d => { emitir('paso.registrado', d); emitir('contadores', d.contadores); actualizarBadge(d.contadores.alertas_activas); })
                .listen('.alerta.creada', d => emitir('alerta.creada', d))
                .listen('.alerta.atendida', d => { emitir('alerta.atendida', d); actualizarBadge(d.activas); })
                .error(() => iniciarSondeo());
            setTimeout(() => { if (!conectado) iniciarSondeo(); }, 6000);
        } catch (e) {
            console.warn('Echo no disponible, se usa sondeo', e);
            iniciarSondeo();
        }
    }

    function actualizarBadge(n) {
        const b = document.getElementById('badge-alertas');
        if (b && n !== undefined) b.textContent = n;
    }

    // Sonido de alerta (Web Audio, sin archivos). Se activa con el interruptor del panel/alertas.
    let audioCtx = null;
    function beep(veces = 3) {
        if (localStorage.getItem('peaje_sonido') !== '1') return;
        audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
        for (let i = 0; i < veces; i++) {
            const o = audioCtx.createOscillator(), g = audioCtx.createGain();
            o.type = 'square'; o.frequency.value = 880; g.gain.value = 0.08;
            o.connect(g); g.connect(audioCtx.destination);
            const t = audioCtx.currentTime + i * 0.25;
            o.start(t); o.stop(t + 0.15);
        }
    }

    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const bs = n => 'Bs ' + Number(n || 0).toFixed(2);

    window.PeajeRT = {
        on(evento, cb) { (oyentes[evento] = oyentes[evento] || []).push(cb); },
        beep, esc, bs,
        placa: p => p ? `<span class="placa">${esc(p)}</span>` : '<span class="placa vacia">ilegible</span>',
        estado: (e, t) => `<span class="badge-estado estado-${esc(e)}">${esc(t || e)}</span>`,
        verEvidencia(url, titulo) {
            if (!url) return;
            document.getElementById('modalEvidenciaImg').src = url;
            document.getElementById('modalEvidenciaTitulo').textContent = titulo || 'Evidencia';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEvidencia')).show();
        },
    };

    PeajeRT.on('alerta.creada', () => beep(3));
    document.addEventListener('click', e => {
        const img = e.target.closest('[data-evidencia]');
        if (img) PeajeRT.verEvidencia(img.dataset.evidencia, img.dataset.titulo);
    });
    document.addEventListener('DOMContentLoaded', iniciarEcho);
})();
