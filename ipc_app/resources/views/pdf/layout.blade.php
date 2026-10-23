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
        display: flex;
        align-items: center;
    }
    .report-header .brand img {
        height: 36px;
        width: auto;
        display: block;
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

    /* Round-column grids (Filling sample table, Packing checklist tables): table-layout: fixed
       + an explicit colgroup keeps every column the exact same width top-to-bottom, and
       vertical-align: middle keeps a short status pill centered even in a row whose neighbour
       column got tall from an embedded photo — without this, those cells looked pinned to the
       top with a big gap underneath, "berantakan" per direct user feedback on the rendered PDF. */
    .record-table { table-layout: fixed; }
    .record-table td, .record-table th { vertical-align: middle; word-wrap: break-word; }
    .record-table .group-row td {
        background: #eef2ff;
        color: #1f2937;
        font-size: 8px;
        font-weight: 800;
        text-transform: uppercase;
        padding: 4px 6px;
    }
    .record-table td.photo-cell { padding: 3px; }
    .record-table td.photo-cell img { display: block; margin: 0 auto; }

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
    .attachment-grid--cols-1 { grid-template-columns: 1fr; }
    .attachment-grid--cols-2 { grid-template-columns: repeat(2, 1fr); }

    /* Smaller photo tile for reports with several photo groups on one page (Filling & Packing) —
       the default 40mm tile is sized for the Startup report's own smaller photo count; reusing it
       as-is here pushed this report onto an extra page. */
    .attachment-tile--sm img, .attachment-tile--sm .placeholder { height: 10mm; }

    /* Tighter table for a long fixed-row grid (e.g. the 30-sample "Verifikasi Sebelum Produksi"
       grid) where the default padding/font-size costs a whole extra page across 30 rows. Tightened
       further 2026-10-22 (direct user request, "maksimal 2 halaman", matching how tightly packed
       the real FR.QAC.182.03 paper form itself is) for the Packing report's own sample/checklist
       grids specifically. */
    .record-table--compact td, .record-table--compact th { padding: 1px 3px; font-size: 7px; }

    /* Labeled photo groups (e.g. "IM Number" vs. "Attach Label and Color check actual in this
       area" vs. "Temperature Setting") — legacy keeps these visually separate rather than one
       flat tile grid, so each group gets its own heading and its own attachment-grid. */
    .photo-groups { display: grid; grid-template-columns: 1fr 2fr; gap: 8px; margin: 4px 0 6px; }
    .photo-group { margin: 0 0 6px; }
    .photo-group-title {
        font-size: 8px;
        font-weight: 800;
        text-transform: uppercase;
        color: #374151;
        margin-bottom: 4px;
        padding-bottom: 2px;
        border-bottom: 1px solid #d1d5db;
    }
    .photo-group .attachment-grid { margin: 0; }

    .sign-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
        margin: 14px 0 6px;
    }
    .sign-grid div {
        position: relative;
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
    /* Optional role caption (e.g. "Operator", "QC IPC") pinned under the signature space —
       used by forms whose sign-off boxes also name who signs each column (Startup Check's two
       sign-grids). Left out of every sign-grid that doesn't opt in via a <small class="role">. */
    .sign-grid .role {
        position: absolute;
        left: 8px;
        right: 8px;
        bottom: 6px;
        display: block;
        font-size: 7px;
        font-weight: 700;
        color: #6b7280;
        text-align: center;
        text-transform: none;
        border-top: 1px solid #d1d5db;
        padding-top: 2px;
    }

    .page-break { break-before: page; page-break-before: always; }

    /* Scoped tightening for the Filling/Packing report only (.fp-compact wraps the whole report
       body — both real paper forms, FR.QAC.192.02 and FR.QAC.182.03, are one-page
       forms, so this report gets a more aggressive squeeze than Startup/Finished's own reports,
       which don't opt into this class. Deliberately excludes .record-table/.record-table--compact
       so it doesn't fight their own (already tighter) padding/font rules. Direct user request
       2026-10-21, best effort to keep this report to one page — see
       approval-filling-packing.blade.php. */
    .fp-compact table:not(.record-table):not(.record-table--compact) { margin: 3px 0 5px; }
    .fp-compact table:not(.record-table):not(.record-table--compact) th,
    .fp-compact table:not(.record-table):not(.record-table--compact) td { padding: 2px 5px; font-size: 8px; }
    .fp-compact .section-title { margin: 6px 0 2px; padding: 2px 6px; font-size: 10px; }
    .fp-compact .info-grid { margin: 3px 0 5px; gap: 3px; }
    .fp-compact .info-grid div { padding: 2px 5px; min-height: 18px; }
    .fp-compact .attachment-grid { margin: 2px 0 5px; }
    .fp-compact .attachment-tile--sm img, .fp-compact .attachment-tile--sm .placeholder { height: 8mm; }
    .fp-compact .sign-grid { margin: 4px 0; }
    .fp-compact .record-table .group-row td { padding: 2px 4px; }
    .fp-compact table.record-table, .fp-compact table.record-table--compact { margin: 3px 0 5px; }
    .fp-compact p { margin: 0 0 3px; }
    /* Dense status pills + photo placeholders so the combined Filling & Packing report fits 2 pages. */
    .fp-compact .status-pill { padding: 0 6px; font-size: 7.5px; line-height: 1.35; }
    .fp-compact .attachment-tile .placeholder { font-size: 7px; }
    .fp-compact .attachment-tile { padding: 1px; }
    .fp-compact .record-table--compact td small, .fp-compact .record-table td small { font-size: 6px; line-height: 1.1; }
    .fp-compact .startup-layout table td, .fp-compact .startup-layout table th { padding: 1px 4px; }
    .fp-compact .nested-table td { padding: 0 4px; }
    .fp-compact .status-pill { padding: 0 5px; font-size: 7px; line-height: 1.2; vertical-align: middle; }
    .fp-compact td, .fp-compact th { line-height: 1.15; }
    .fp-compact .attachment-tile .placeholder { height: 5mm; }
    .fp-compact .attachment-tile--sm img { height: 8mm; }
    .fp-compact .attachment-tile figcaption { padding: 0; }
    .fp-compact .attachment-tile img { height: 14mm; }
    .fp-compact .photo-group .attachment-grid, .fp-compact .attachment-grid { gap: 4px; }
    .fp-compact .sign-grid div { padding-top: 3px; }
    .fp-compact .sign-grid strong { margin-top: 4px; }
    .fp-compact .sign-grid--tight div { padding-bottom: 4px; }
    .fp-compact .record-table td.photo-cell { padding: 0 3px; }
    .fp-compact .record-table td, .fp-compact .record-table th { padding-top: 0; padding-bottom: 0; }
    .fp-compact .nested-table td { font-size: 7px; padding: 0 4px; line-height: 1.1; }
    .fp-compact .nested-cell { padding: 0 4px; }
    .fp-compact p.muted { font-size: 6.5px !important; margin: 0 0 2px; line-height: 1.15; }
    .fp-compact .photo-groups { margin: 2px 0; }
    .fp-compact .photo-group-title { margin-bottom: 2px; }
    .fp-compact .attachment-tile { margin: 0; }
    .fp-compact .section-title { break-after: avoid; page-break-after: avoid; }
    /* Portrait Fillpack page has room to spare: looser rows than the 1-page-squeeze defaults. */
    .fp-form .record-table td, .fp-form .record-table th { padding-top: 1.5px; padding-bottom: 1.5px; }
    /* Finished Good (FR.QAC.193.00 page 2) is a landscape form: photos in one row, tighter tiles. */
    .fg-compact .attachment-grid { grid-template-columns: repeat(6, 1fr); }
    .fg-compact .attachment-tile--sm img, .fg-compact .attachment-tile--sm .placeholder { height: 14mm; }
    .fg-compact .two-col { align-items: start; }
    .fg-compact .two-col > * { min-width: 0; }
    .fg-compact .section-title { break-after: avoid; page-break-after: avoid; }
    .fg-compact table { break-inside: avoid; }
    .muted { color: #6b7280; }
    .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

    /* Startup Check's legacy layout puts the checklist table beside the sign-off boxes — photos
       stay full-width above (not squeezed into this column) so they stay legible. See
       approval-startup.blade.php. */
    .startup-layout { display: grid; grid-template-columns: 74% 24%; gap: 10px; align-items: start; }
    .startup-side { display: flex; flex-direction: column; gap: 8px; }
    .sign-grid--stack { grid-template-columns: 1fr; gap: 6px; margin: 0; }
    /* No role caption used (no <small class="role">) — the base .sign-grid div's 26px bottom
       padding exists purely to reserve room for that caption, which is otherwise wasted space. */
    .sign-grid--tight div { padding: 3px 6px; }
    .fp-compact .sign-grid--tight { margin: 3px 0 !important; }
    /* .sign-grid--tight's 3px bottom padding is fine for a box with no role caption (e.g. the
       Issued By/Review By/QR block, which only has a plain sign-date), but a role caption is
       absolutely positioned and needs real room reserved under the signer's name or it visually
       crowds it — direct user report ("ttd Tamara terlalu rapat"), real screenshot, 2026-10-22.
       Combine with .sign-grid--tight (not a replacement for it) on any box that uses <small
       class="role">. */
    .sign-grid--role-tight div { padding-bottom: 18px; }
    .sign-grid--cols-2 { grid-template-columns: repeat(2, 1fr); }

    /* Sign-off box's actual signer value (a name, or a decision date) — distinct from the plain
       label (<span>) above it and the role caption (<small class="role">) pinned to the bottom. */
    .sign-grid strong { display: block; margin-top: 10px; font-size: 9px; }
    .sign-grid .sign-date { display: block; margin-top: 2px; font-size: 7.5px; color: #6b7280; }

    /* Verified-QR sign-off box — a scannable stamp linking to the public verification page
       (App\Services\Verification\VerificationQrCode / VerificationController), sitting alongside
       the plain sign-off boxes above. Own class (not a plain .sign-grid div) since it centers an
       <svg> instead of text. */
    .sign-grid .qr-box { display: flex; flex-direction: column; align-items: center; padding-bottom: 6px; }
    .sign-grid .qr-box span { text-align: center; }
    .sign-grid .qr-box .qr-code svg { width: 52px; height: 52px; display: block; margin: 4px 0 2px; }
    .sign-grid .qr-box small { font-size: 7px; color: #6b7280; text-align: center; }

    /* Nested sub-table for a checklist row that groups several sub-items (e.g. "Check Detection
       Machine" bundling 5 individual machine checks) — sits inside a single <td>, no outer border
       of its own since the parent cell already has one. */
    .nested-cell { padding: 1px 4px; }
    .nested-table { width: 100%; border-collapse: collapse; margin: 0; table-layout: fixed; }
    .nested-table td { border: none; border-bottom: 1px solid #e5e7eb; padding: 1px 4px; font-size: 7.5px; white-space: nowrap; overflow: hidden; }
    .nested-table td:first-child { width: 55%; }
    .nested-table tr:last-child td { border-bottom: none; }
</style>
@stack('head')
</head>
<body>
    @php
        $logoPath = resource_path('images/cosmax-idn-logo.jpg');
        $logoDataUri = is_file($logoPath)
            ? 'data:image/jpeg;base64,'.base64_encode(file_get_contents($logoPath))
            : null;
    @endphp
    <div class="report-header">
        <div class="brand">
            @if ($logoDataUri)
                <img src="{{ $logoDataUri }}" alt="Cosmax">
            @else
                IPC<br><span style="font-weight:400;font-size:8px;">In Process Control</span>
            @endif
        </div>
        <div>
            <div class="title">{{ $title ?? 'Report' }}</div>
            <div style="text-align:center;"><span class="approval-badge">Approval</span></div>
        </div>
        <div class="meta">
            @isset($docNumber)
                <div class="form-number">No. Doc: {{ $docNumber }}</div>
                <div>Rev: {{ $docRevision ?? '0' }}</div>
            @else
                <div class="form-number">No. Batch: {{ $batch->no_batch }}</div>
            @endisset
            <div>{{ now()->translatedFormat('d M Y H:i') }}</div>
        </div>
    </div>

    <div class="info-grid">
        <div><span>No. Batch</span><strong>{{ $batch->no_batch }}</strong></div>
        <div><span>FG Code</span><strong>{{ $batch->masterProduct->fg_code ?? '—' }}</strong></div>
        <div><span>Bulk Code</span><strong>{{ $batch->bulk_code ?? '—' }}</strong></div>
        <div><span>Line</span><strong>{{ $batch->masterLine->name ?? '—' }} ({{ $batch->masterLine->code ?? '—' }})</strong></div>
        <div style="grid-column: span 4;"><span>Nama Produk</span><strong>{{ $batch->masterProduct->product_name ?? '—' }}</strong></div>
    </div>

    @yield('content')
</body>
</html>
