<?php

namespace App\Events;

use App\Models\Alerta;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class AlertaCreada implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public array $alerta;

    public function __construct(Alerta $alerta)
    {
        $this->alerta = $alerta->resumen();
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('peaje')];
    }

    public function broadcastAs(): string
    {
        return 'alerta.creada';
    }
}
