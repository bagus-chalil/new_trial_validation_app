<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<meta http-equiv="refresh" content="30">
<title>Status Line {{ $line->code }}</title>
<style>
    * { box-sizing: border-box; }
    body { margin: 0; padding: 20px 14px; font-family: 'Segoe UI', Arial, sans-serif; background: #f3f4f6; color: #111827; }
    .wrap { max-width: 520px; margin: 0 auto; }
    .brand { font-size: 12px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: #2f6fed; }
    h1 { margin: 4px 0 2px; font-size: 22px; }
    .sub { color: #6b7280; font-size: 13px; margin-bottom: 16px; }
    .card { background: #fff; border-radius: 18px; padding: 16px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
    .stage { display: inline-block; padding: 4px 10px; border-radius: 999px; background: #e8f0fe; color: #1d4ed8; font-size: 12.5px; font-weight: 700; }
    .batch { font-size: 20px; font-weight: 800; margin: 8px 0 2px; }
    .product { font-size: 14px; font-weight: 600; }
    dl { display: grid; grid-template-columns: auto 1fr; gap: 6px 14px; margin: 12px 0 0; font-size: 13px; }
    dt { color: #6b7280; font-weight: 600; }
    dd { margin: 0; font-weight: 600; }
    .bar { display: flex; gap: 4px; margin-top: 10px; }
    .bar i { flex: 1; height: 6px; border-radius: 3px; background: #e5e7eb; }
    .bar i.on { background: #2f6fed; }
    .idle { text-align: center; color: #6b7280; padding: 36px 16px; }
    .foot { text-align: center; color: #9ca3af; font-size: 11.5px; margin-top: 14px; }
</style>
</head>
<body>
<div class="wrap">
    <div class="brand">IPC · Status Line</div>
    <h1>{{ $line->name }}</h1>
    <div class="sub">{{ $line->code }} · {{ $line->category }} · {{ $line->area }}</div>

    @forelse ($batches as $b)
        <div class="card">
            <span class="stage">{{ $b['stage_label'] }}</span>
            <div class="batch">{{ $b['no_batch'] }}</div>
            <div class="product">{{ $b['product_name'] ?? '-' }}</div>
            @if ($b['step'])
                <div class="bar">
                    @for ($i = 1; $i <= $b['total_steps']; $i++)<i class="{{ $i <= $b['step'] ? 'on' : '' }}"></i>@endfor
                </div>
            @endif
            <dl>
                <dt>FG Code</dt><dd>{{ $b['fg_code'] ?? '-' }}</dd>
                <dt>PIC</dt><dd>{{ $b['pic'] ?? '-' }}</dd>
                <dt>Aktivitas terakhir</dt>
                <dd>{{ $b['last_activity_at'] ? \Illuminate\Support\Carbon::parse($b['last_activity_at'])->timezone(config('app.timezone'))->format('d M Y H:i') : '-' }}</dd>
            </dl>
        </div>
    @empty
        <div class="card idle"><strong>Line idle</strong><br>Tidak ada batch yang sedang berjalan di line ini.</div>
    @endforelse

    <div class="foot">Diperbarui {{ now()->format('d M Y H:i:s') }} · refresh otomatis 30 detik</div>
</div>
</body>
</html>
