<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>{{ $title ?? 'IPC Report' }}</title>
<style>
    * { box-sizing: border-box; }
    body {
        margin: 0;
        font-family: 'Segoe UI', Arial, sans-serif;
        color: #111827;
        font-size: 10px;
        line-height: 1.4;
    }
    h1, h2, h3, h4 { margin: 0 0 4px; color: #111827; }
    p { margin: 0 0 4px; }

    .report-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        border-bottom: 2px solid #111827;
        padding-bottom: 8px;
        margin-bottom: 12px;
    }
    .report-header .brand {
        font-size: 13px;
        font-weight: 800;
        color: #2f6fed;
    }
    .report-header .title {
        text-align: center;
        font-size: 15px;
        font-weight: 900;
        color: #111827;
        flex: 1;
        text-transform: uppercase;
    }
    .report-header .meta {
        text-align: right;
        font-size: 9px;
        color: #6b7280;
    }
    .report-header .form-number {
        font-weight: 900;
        color: #111827;
    }
    .approval-badge {
        display: inline-block;
        margin-top: 3px;
        padding: 3px 10px;
        border-radius: 999px;
        font-weight: 800;
        font-size: 9px;
        letter-spacing: .04em;
        text-transform: uppercase;
        background: #2f6fed;
        color: #fff;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin: 6px 0 12px;
    }
    th, td {
        border: 1px solid #9ca3af;
        padding: 4px 6px;
        text-align: left;
        vertical-align: top;
        font-size: 9px;
    }
    th {
        background: #eef2ff;
        color: #1f2937;
        font-size: 8px;
        text-transform: uppercase;
        font-weight: 800;
    }
    tr.not-conform td, tr.reject td { background: #ffe8e8; }
    td.center, th.center { text-align: center; }

    .status-pill {
        display: inline-block;
        padding: 1px 7px;
        border-radius: 999px;
        font-weight: 700;
        font-size: 8.5px;
        border: 1px solid #d1d5db;
    }
    .status-pill.ok { background: #eaf7ee; color: #14532d; border-color: #bbf0c9; }
    .status-pill.bad { background: #fdecec; color: #7f1d1d; border-color: #f8c6c6; }
    .status-pill.na { background: #fff4e5; color: #7c4a03; border-color: #fcd9a4; }
    .status-pill.muted { background: #f3f4f6; color: #6b7280; border-color: #e5e7eb; }

    .section-title {
        font-size: 11.5px;
        font-weight: 800;
        margin: 12px 0 4px;
        padding: 4px 8px;
        background: #f3f4f6;
        border-left: 4px solid #2f6fed;
        text-transform: uppercase;
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 4px;
        margin: 6px 0 10px;
    }
    .info-grid div {
        border: 1px solid #d1d5db;
        border-radius: 4px;
        padding: 4px 6px;
        min-height: 30px;
    }
    .info-grid span {
        display: block;
        color: #6b7280;
        font-size: 7px;
        font-weight: 800;
        text-transform: uppercase;
    }
    .info-grid strong {
        display: block;
        margin-top: 2px;
        font-size: 9px;
        color: #111827;
    }

    .attachment-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 6px;
        margin: 4px 0 10px;
    }
    .attachment-tile {
        break-inside: avoid;
        page-break-inside: avoid;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        padding: 3px;
        text-align: center;
    }
    .attachment-tile img {
        width: 100%;
        height: 40mm;
        object-fit: contain;
        display: block;
        margin: 0 auto;
    }
    .attachment-tile figcaption {
        margin: 0;
        font-size: 7px;
        color: #6b7280;
        padding: 2px;
    }
    .attachment-tile .placeholder {
        height: 40mm;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #9ca3af;
        font-style: italic;
    }

    .sign-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
        margin: 14px 0 6px;
    }
    .sign-grid div {
        border: 1px solid #9ca3af;
        border-radius: 4px;
        padding: 6px 8px 26px;
    }
    .sign-grid span {
        display: block;
        font-size: 8px;
        font-weight: 800;
        text-transform: uppercase;
        color: #374151;
    }

    .page-break { break-before: page; page-break-before: always; }
    .muted { color: #6b7280; }
    .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
</style>
</head>
<body>
    <div class="report-header">
        <div class="brand">IPC<br><span style="font-weight:400;font-size:8px;">In Process Control</span></div>
        <div>
            <div class="title">{{ $title ?? 'Report' }}</div>
            <div style="text-align:center;"><span class="approval-badge">Approval</span></div>
        </div>
        <div class="meta">
            <div class="form-number">No. Batch: {{ $batch->no_batch }}</div>
            <div>{{ now()->translatedFormat('d M Y H:i') }}</div>
        </div>
    </div>

    <div class="info-grid">
        <div><span>No. Batch</span><strong>{{ $batch->no_batch }}</strong></div>
        <div><span>FG Code</span><strong>{{ $batch->masterProduct->fg_code ?? '—' }}</strong></div>
        <div><span>Bulk Code</span><strong>{{ $batch->masterProduct->bulk_code ?? '—' }}</strong></div>
        <div><span>Line</span><strong>{{ $batch->masterLine->name ?? '—' }} ({{ $batch->masterLine->code ?? '—' }})</strong></div>
        <div style="grid-column: span 4;"><span>Nama Produk</span><strong>{{ $batch->masterProduct->product_name ?? '—' }}</strong></div>
    </div>

    @yield('content')
</body>
</html>
