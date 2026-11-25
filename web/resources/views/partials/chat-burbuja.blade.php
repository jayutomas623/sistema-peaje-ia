<button id="burbuja-chat-btn" class="btn btn-primary" title="Agente Peaje (chat)"><i class="bi bi-chat-dots-fill"></i></button>
<div id="burbuja-chat-panel" class="card" data-chat>
    <div class="card-header text-bg-primary d-flex align-items-center">
        <i class="bi bi-robot me-2"></i><strong>Agente Peaje</strong>
        <button type="button" class="btn btn-sm btn-outline-light ms-auto" data-limpiar title="Nueva conversación"><i class="bi bi-arrow-counterclockwise"></i></button>
    </div>
    <div class="chat-mensajes"></div>
    @include('partials.chat-form')
</div>
