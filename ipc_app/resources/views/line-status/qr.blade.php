<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>QR Line {{ $line->code }}</title>
<style>
    body { margin: 0; padding: 40px 20px; font-family: 'Segoe UI', Arial, sans-serif; text-align: center; color: #111827; }
    .brand { font-size: 14px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; color: #2f6fed; }
    h1 { font-size: 40px; margin: 8px 0 4px; }
    .sub { font-size: 18px; color: #6b7280; margin-bottom: 24px; }
    .qr svg { width: 320px; height: 320px; }
    .hint { font-size: 18px; font-weight: 600; margin-top: 18px; }
    .url { font-size: 11px; color: #9ca3af; margin-top: 8px; word-break: break-all; }
    @media print { .noprint { display: none; } }
</style>
</head>
<body>
    <div class="brand">IPC · Status Line</div>
    <h1>{{ $line->name }}</h1>
    <div class="sub">{{ $line->code }} · {{ $line->category }} · {{ $line->area }}</div>
    <div class="qr">{!! $svg !!}</div>
    <div class="hint">Scan untuk melihat status IPC di line ini</div>
    <div class="url">{{ $url }}</div>
    <p class="noprint"><button onclick="window.print()" style="padding:10px 20px;font-size:15px">Cetak</button></p>
</body>
</html>
