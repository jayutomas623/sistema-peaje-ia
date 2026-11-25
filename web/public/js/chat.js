/* Chat del agente (página /chat y burbuja flotante). Texto + imagen, historial en sessionStorage. */
(function () {
    const C = window.PEAJE || {};
    const CLAVE = 'peaje_chat_historial';
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function historial() {
        try { return JSON.parse(sessionStorage.getItem(CLAVE) || '[]'); } catch (e) { return []; }
    }
    function guardar(h) {
        try { sessionStorage.setItem(CLAVE, JSON.stringify(h.slice(-30))); } catch (e) { /* almacenamiento lleno */ }
    }

    const ETIQUETAS = { 1: ['success', 'Registrada con paso'], 2: ['primary', 'Registrada sin paso'],
        3: ['danger', 'No registrada · posible evasión'], 4: ['secondary', 'No existe'] };

    function crearChat(raiz) {
        const lista = raiz.querySelector('.chat-mensajes');
        const form = raiz.querySelector('form');
        const input = form.querySelector('input[name=mensaje]');
        const archivo = form.querySelector('input[type=file]');
        const vista = raiz.querySelector('.chat-adjunto');

        function pintar(m) {
            const div = document.createElement('div');
            div.className = 'chat-msg ' + m.rol;
            let html = esc(m.texto);
            if (m.estado_codigo && ETIQUETAS[m.estado_codigo]) {
                const [c, t] = ETIQUETAS[m.estado_codigo];
                html = `<span class="badge text-bg-${c} mb-1">${t}</span>\n` + html;
            }
            if (m.sugerencias && m.sugerencias.length) {
                html += '\n' + m.sugerencias.map(s => `<button type="button" class="btn btn-sm btn-outline-primary chat-sugerencia mt-1 me-1" data-placa="${esc(s)}">${esc(s)}</button>`).join('');
            }
            if (m.imagen) html += `<img src="${m.imagen}" alt="imagen">`;
            if (m.fuente) html += `<span class="fuente">${esc(m.fuente)}</span>`;
            div.innerHTML = html;
            lista.appendChild(div);
            lista.scrollTop = lista.scrollHeight;
        }

        function render() {
            lista.innerHTML = '';
            const h = historial();
            if (!h.length) pintar({ rol: 'agente', texto: 'Hola 👋 Soy el Agente Peaje. Escribe una placa (ej. 1151DKL) o envíame una foto de una placa.' });
            h.forEach(pintar);
        }

        lista.addEventListener('click', e => {
            const b = e.target.closest('.chat-sugerencia');
            if (b) { input.value = '¿Pasó la placa ' + b.dataset.placa + '?'; form.requestSubmit(); }
        });
        archivo.addEventListener('change', () => {
            vista.textContent = archivo.files[0] ? '📎 ' + archivo.files[0].name : '';
        });

        form.addEventListener('submit', async e => {
            e.preventDefault();
            const texto = input.value.trim();
            const img = archivo.files[0];
            if (!texto && !img) return;
            const h = historial();
            const previa = img ? URL.createObjectURL(img) : null;
            const mUsuario = { rol: 'usuario', texto: texto || '(imagen de placa)' };
            pintar(previa ? { ...mUsuario, imagen: previa } : mUsuario);
            const fd = new FormData();
            if (texto) fd.append('mensaje', texto);
            if (img) fd.append('imagen', img);
            fd.append('historial', JSON.stringify(h.map(x => ({ rol: x.rol, texto: x.texto })).slice(-6)));
            input.value = ''; archivo.value = ''; vista.textContent = '';
            const esp = document.createElement('div');
            esp.className = 'chat-msg agente';
            esp.innerHTML = '<span class="punto-escribiendo"></span><span class="punto-escribiendo"></span><span class="punto-escribiendo"></span>';
            lista.appendChild(esp); lista.scrollTop = lista.scrollHeight;
            try {
                const r = await fetch(C.chatUrl, { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': C.csrf, Accept: 'application/json' } });
                const d = await r.json();
                esp.remove();
                const m = r.ok ? { rol: 'agente', texto: d.respuesta, estado_codigo: d.estado_codigo, sugerencias: d.sugerencias,
                    fuente: d.fuente, imagen: d.imagen_anotada } : { rol: 'agente', texto: d.message || 'Error al consultar al agente.' };
                pintar(m);
                h.push(mUsuario);
                h.push({ ...m, imagen: null });
                guardar(h);
            } catch (err) {
                esp.remove();
                pintar({ rol: 'agente', texto: 'No hay conexión con el servidor. Intenta de nuevo.' });
            }
        });

        raiz.querySelector('[data-limpiar]')?.addEventListener('click', () => { sessionStorage.removeItem(CLAVE); render(); });
        render();
        return { render };
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-chat]').forEach(crearChat);
        const btn = document.getElementById('burbuja-chat-btn');
        const panel = document.getElementById('burbuja-chat-panel');
        if (btn && panel) {
            btn.addEventListener('click', () => {
                panel.classList.toggle('abierto');
                if (panel.classList.contains('abierto')) panel.querySelector('input[name=mensaje]').focus();
            });
        }
    });
})();
