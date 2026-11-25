<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class AlertaAtendida implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $alertaId, public int $activas)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('peaje')];
    }

    public function broadcastAs(): string
    {
        return 'alerta.atendida';
    }
}
