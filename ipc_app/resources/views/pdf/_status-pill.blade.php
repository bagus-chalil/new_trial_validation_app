@php
    $v = $value ?? null;
    $class = 'muted';
    if ($v) {
        if (preg_match('/\bnot\b|reject/i', $v)) {
            $class = 'bad';
        } elseif ($v === 'N/A' || $v === 'Hold') {
            $class = 'na';
        } else {
            $class = 'ok';
        }
    }
@endphp
<span class="status-pill {{ $class }}">{{ $v ?: '—' }}</span>
