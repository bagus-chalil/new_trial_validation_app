<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>{{ $title ?? 'Report' }}</title>
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
        color: #1f55b5;
    }
    .report-header .title {
        text-align: center;
        font-size: 15px;
        font-weight: 900;
        color: #111827;
        flex: 1;
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

    .badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 999px;
        font-weight: 700;
        font-size: 9px;
        border: 1px solid #d1d5db;
        background: #eef2f7;
        color: #475467;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin: 6px 0 12px;
    }
    th, td {
        border: 1px solid #d1d5db;
        padding: 4px 6px;
        text-align: left;
        vertical-align: top;
        font-size: 9px;
    }
    th {
        background: #f8fafc;
        color: #475467;
        font-size: 8px;
        text-transform: uppercase;
        font-weight: 800;
    }
    tr.notok td { background: #ffe8e8; }

    .section-title {
        font-size: 11px;
        font-weight: 800;
        margin: 10px 0 4px;
        padding-bottom: 2px;
        border-bottom: 1px solid #d1d5db;
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
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

    .attachment-category {
        break-inside: avoid;
        page-break-inside: avoid;
        margin-bottom: 10px;
    }
    .attachment-category h4 {
        margin: 0 0 4px;
        font-size: 10px;
    }
    .attachment-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 6px;
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
        height: 55mm;
        object-fit: contain;
        display: block;
        margin: 0 auto;
    }
    .attachment-tile figcaption {
        margin: 0;
        font-size: 7px;
        color: #6b7280;
        padding: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .page-break { break-before: page; page-break-before: always; }
    .muted { color: #6b7280; }
    .stats-row { display: flex; flex-wrap: wrap; gap: 6px; margin: 4px 0 8px; }
    .stats-row span { border: 1px solid #d1d5db; padding: 2px 6px; border-radius: 4px; background: #f8fafc; }
</style>
</head>
<body>
    <div class="report-header">
        <div class="brand">Cosmax<br><span style="font-weight:400;font-size:8px;">Trial Validation System</span></div>
        <div class="title">{{ $title ?? 'Report' }}</div>
        <div class="meta">
            <div class="form-number">FR.QSE.074.04</div>
            <div>{{ now()->translatedFormat('d M Y H:i') }}</div>
        </div>
    </div>

    @yield('content')
</body>
</html>
