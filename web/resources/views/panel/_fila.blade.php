<tr data-id="{{ $p['id'] }}">
    <td>
        @if ($p['evidencia_url'])
            <img src="{{ $p['evidencia_url'] }}" class="evidencia-mini" data-evidencia="{{ $p['evidencia_url'] }}"
                 data-titulo="Paso #{{ $p['id'] }} · {{ $p['placa'] ?? 'ilegible' }}" alt="evidencia" loading="lazy">
        @else
            <div class="evidencia-mini d-flex align-items-center justify-content-center text-secondary"><i class="bi bi-image"></i></div>
        @endif
    </td>
    <td>
        @if ($p['placa'])<span class="placa">{{ $p['placa'] }}</span>@else<span class="placa vacia">ilegible</span>@endif
        @if ($p['propietario'])<div class="small text-secondary text-truncate" style="max-width:160px">{{ $p['propietario'] }}</div>@endif
    </td>
    <td class="text-capitalize">{{ $p['categoria'] ?? '—' }}</td>
    <td class="text-center">{{ $p['ejes'] ?? '—' }}</td>
    <td class="text-center">{{ $p['pasajeros'] ?? '—' }}</td>
    <td class="text-end fw-semibold">Bs {{ number_format($p['monto'], 2) }}</td>
    <td><span class="badge-estado estado-{{ $p['estado'] }}">{{ $p['estado_texto'] }}</span></td>
    <td class="small">{{ $p['caseta'] }}<br><span class="text-secondary">{{ \Illuminate\Support\Carbon::parse($p['fecha'])->format('d/m H:i:s') }}</span></td>
</tr>
