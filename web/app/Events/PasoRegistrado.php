<?php

namespace App\Events;

use App\Models\Paso;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Se emite a Pusher/Reverb en el canal privado "peaje" cada vez que se registra un paso. */
class PasoRegistrado implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public array $paso;

    public array $contadores;

    public function __construct(Paso $paso, array $contadores)
    {
        $this->paso = $paso->resumen();
        $this->contadores = $contadores;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('peaje')];
    }

    public function broadcastAs(): string
    {
        return 'paso.registrado';
    }
}
