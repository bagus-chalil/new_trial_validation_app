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

    // Optional short form for dense multi-column grids (e.g. Filling/Packing's one-column-per-
    // TH_PROGRESS-round tables) where the full word wraps onto two lines and breaks row height
    // alignment — see the legend printed near each grid that uses this. Left untouched (full
    // word) for every other call site, and for any value outside this known set, so this stays
    // safe to pass on a pill whose vocabulary isn't Conform/Not Conform (e.g. Startup Check's
    // Available/Not Available, or a decision like Passed/Hold/Reject).
    $display = $v ?: '—';
    if (($abbreviate ?? false) && $v) {
        $display = match ($v) {
            'Conform' => 'CF',
            'Not Conform' => 'NC',
            default => $v,
        };
    }
@endphp
<span class="status-pill {{ $class }}">{{ $display }}</span>
