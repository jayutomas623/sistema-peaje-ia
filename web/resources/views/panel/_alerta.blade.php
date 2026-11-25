<li class="list-group-item" data-id="{{ $a['id'] }}">
    <div class="d-flex"><strong class="text-danger small">{{ $a['tipo_texto'] }}</strong>
        <span class="ms-auto small text-secondary">{{ \Illuminate\Support\Carbon::parse($a['fecha'])->format('d/m H:i') }}</span></div>
    <div class="small">{{ $a['mensaje'] }}</div>
    <div class="small text-secondary">{{ $a['caseta'] }}</div>
</li>
