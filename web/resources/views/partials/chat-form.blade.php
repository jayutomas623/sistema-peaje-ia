<form class="border-top p-2 bg-white" autocomplete="off">
    <div class="small text-secondary chat-adjunto px-1"></div>
    <div class="input-group">
        <label class="btn btn-outline-secondary mb-0" title="Adjuntar o tomar foto de una placa">
            <i class="bi bi-camera"></i>
            <input type="file" accept="image/*" capture="environment" hidden>
        </label>
        <input type="text" name="mensaje" class="form-control" placeholder="Ej.: ¿pasó la 1151-DKL hoy?" maxlength="2000">
        <button class="btn btn-primary" type="submit"><i class="bi bi-send"></i></button>
    </div>
</form>
