<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Verifikasi — {{ $batch->no_batch }}</title>
<style>
    * { box-sizing: border-box; }
    body {
        margin: 0;
        padding: 28px 16px;
        font-family: 'Segoe UI', Arial, sans-serif;
        background: #f3f4f6;
        color: #111827;
    }
    .card {
        max-width: 420px;
        margin: 0 auto;
        background: #fff;
        border-radius: 18px;
        box-shadow: 0 1px 3px rgba(0,0,0,.08), 0 8px 24px rgba(0,0,0,.05);
        overflow: hidden;
    }
    .brand {
        text-align: center;
        padding: 18px 20px 0;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #2f6fed;
    }
    .status {
        text-align: center;
        padding: 10px 20px 18px;
    }
    .status .icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 56px;
        height: 56px;
        border-radius: 999px;
        font-size: 28px;
        line-height: 1;
        margin-bottom: 8px;
    }
    .status.ok .icon { background: #eaf7ee; color: #14532d; }
    .status.bad .icon { background: #fdecec; color: #7f1d1d; }
    .status.pending .icon { background: #f3f4f6; color: #6b7280; }
    .status h1 {
        margin: 0;
        font-size: 17px;
        font-weight: 800;
    }
    .status.ok h1 { color: #14532d; }
    .status.bad h1 { color: #7f1d1d; }
    .status.pending h1 { color: #6b7280; }
    .status p {
        margin: 4px 0 0;
        font-size: 12.5px;
        color: #6b7280;
    }
    .divider { height: 1px; background: #e5e7eb; }
    dl {
        margin: 0;
        padding: 16px 20px;
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 8px 14px;
        font-size: 13px;
    }
    dt {
        color: #6b7280;
        font-weight: 600;
        white-space: nowrap;
    }
    dd {
        margin: 0;
        font-weight: 700;
        color: #111827;
        text-align: right;
    }
    .remarks {
        margin: 0 20px 16px;
        padding: 10px 12px;
        background: #fdecec;
        border: 1px solid #f8c6c6;
        border-radius: 10px;
        font-size: 12.5px;
        color: #7f1d1d;
    }
    .footer {
        padding: 12px 20px 18px;
        text-align: center;
        font-size: 10.5px;
        color: #9ca3af;
    }
</style>
</head>
<body>
    @php
        $decided = $approval !== null;
        $approved = $decided && $approval->decision === \App\Models\IpcApproval::DECISION_APPROVED;
        $statusClass = ! $decided ? 'pending' : ($approved ? 'ok' : 'bad');
    @endphp

    <div class="card">
        <div class="brand">Verifikasi Dokumen IPC</div>

        <div class="status {{ $statusClass }}">
            <div class="icon">
                @if (! $decided)
                    ?
                @elseif ($approved)
                    &#10003;
                @else
                    &#10007;
                @endif
            </div>
            <h1>
                @if (! $decided)
                    Belum Ada Approval
                @elseif ($approved)
                    Dokumen Terverifikasi
                @else
                    Dokumen Ditolak
                @endif
            </h1>
            <p>
                @if (! $decided)
                    Stage ini belum diputuskan (Approve/Reject) di sistem IPC.
                @elseif ($approved)
                    Stage ini telah disetujui secara resmi di sistem IPC.
                @else
                    Stage ini ditandai Rejected di sistem IPC.
                @endif
            </p>
        </div>

        <div class="divider"></div>

        <dl>
            <dt>No. Batch</dt><dd>{{ $batch->no_batch }}</dd>
            <dt>FG Code</dt><dd>{{ $batch->masterProduct->fg_code ?? '—' }}</dd>
            <dt>Nama Produk</dt><dd>{{ $batch->masterProduct->product_name ?? '—' }}</dd>
            <dt>Line</dt><dd>{{ $batch->masterLine->name ?? '—' }}</dd>
            <dt>Tahap</dt><dd>{{ $stageLabel }}</dd>
            @if ($decided)
                <dt>Keputusan</dt><dd>{{ $approval->decision }}</dd>
                <dt>Oleh</dt><dd>{{ $approval->approver->name ?? '—' }}</dd>
                <dt>Waktu</dt><dd>{{ optional($approval->approved_at)->translatedFormat('d M Y H:i') ?? '—' }}</dd>
            @endif
        </dl>

        @if ($decided && ! $approved && $approval->remarks)
            <p class="remarks"><strong>Catatan:</strong> {{ $approval->remarks }}</p>
        @endif

        <div class="footer">
            Dicek pada {{ now()->translatedFormat('d M Y H:i') }} &middot; Sistem IPC
        </div>
    </div>
</body>
</html>
