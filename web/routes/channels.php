<?php

use App\Models\Usuario;
use Illuminate\Support\Facades\Broadcast;

// Canal privado del panel en tiempo real: solo usuarios de la lista blanca activos.
Broadcast::channel('peaje', fn (Usuario $usuario) => $usuario->activo);
