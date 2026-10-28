{{--
    "PEMERIKSAAN FORMAT CODING" + "PEMERIKSAAN BERAT PRODUK" — part of the Start Up section of the
    paper forms (FR.QAC.182.03 / 193.00):
      - Format Penulisan Coding / Operator (Actual Coding): startup_checks.coding_format_* and the
        per-tier coding photos.
      - Berat Masterbox: the Start Inspection weight_master_box samples (only for flows that have
        a Packing stage); Standar Berat Masterbox is the Std Bruto MB typed on Packing Check.
      - Appearance / Uji Kebocoran: their own Startup Check inputs (product_appearance_status /
        product_leakage_test_status), distinct from the numbered checklist's Vacuum Test and
        Appearance, Coding, Attribute items. Uji Fungsional: the Packing Check's Primary
        Functional Test, as before.
    Needs: $batch, $startupCheck, $startupInspection, $photoUrls.
--}}
{{-- $produkSection: 'all' (default) | 'coding' | 'berat' - the Full flow's Startup form puts
     Verifikasi Sebelum Produksi between the two (user decision 2026-10-28). --}}
@php $produkSection = $produkSection ?? 'all'; @endphp
@if ($produkSection !== 'berat')
<div class="section-title" style="margin-top: 4px;">Pemeriksaan Format Coding</div>
<table>
    <thead>
        <tr><th style="width: 18%;">Keterangan</th><th>Primer</th><th>Sekunder</th><th>Tersier</th></tr>
    </thead>
    <tbody>
        <tr>
            <td>Format Penulisan Coding dan Penjelasan</td>
            <td style="white-space: pre-line;">{{ $startupCheck?->coding_format_primary ?: '—' }}</td>
            <td style="white-space: pre-line;">{{ $startupCheck?->coding_format_secondary ?: '—' }}</td>
            <td style="white-space: pre-line;">{{ $startupCheck?->coding_format_tersier ?: '—' }}</td>
        </tr>
        <tr>
            <td>Operator (Actual Coding)</td>
            @foreach (['primary', 'secondary', 'tersier'] as $tier)
                @php $actualCodingPhoto = $photoUrls['startup']['coding_'.$tier] ?? null; @endphp
                <td style="text-align: center;">
                    @if ($startupCheck?->{'coding_operator_na_'.$tier})
                        N/A
                    @elseif ($actualCodingPhoto)
                        <img src="{{ $actualCodingPhoto }}" alt="Operator (Actual Coding) {{ $tier }}" style="max-height: 14mm; max-width: 100%;">
                    @else
                        <span class="muted">—</span>
                    @endif
                </td>
            @endforeach
        </tr>
        <tr><td>Line Leader</td><td colspan="3">{{ $startupCheck?->line_leader_name ?: '—' }}</td></tr>
        <tr><td>QC IPC</td><td colspan="3">{{ $startupCheck?->user->name ?? '—' }}</td></tr>
    </tbody>
</table>

@endif

@if ($produkSection !== 'coding')
<div class="section-title">Pemeriksaan Berat Produk</div>
@if ($batch->hasPackingStage())
    @php $weightSamplesByNo = $startupInspection?->samples->keyBy('sample_no') ?? collect(); @endphp
    <table>
        <tbody>
            <tr><td style="width: 18%;"><strong>Standar Berat Masterbox</strong></td><td>{{ $batch->packingCheck?->standard_weight_mb ?? '—' }}</td></tr>
        </tbody>
    </table>
    @include('pdf._packing-sample-grid', ['label' => 'Berat Masterbox', 'samplesByNo' => $weightSamplesByNo, 'field' => 'weight_master_box'])
@endif

<table>
    <tbody>
        <tr><td style="width: 18%;"><strong>Appereance</strong></td><td>@include('pdf._status-pill', ['value' => $startupCheck?->product_appearance_status])</td></tr>
        <tr><td><strong>Uji Kebocoran</strong></td><td>@include('pdf._status-pill', ['value' => $startupCheck?->product_leakage_test_status])</td></tr>
        @if ($batch->hasPackingStage())
        <tr><td><strong>Uji Fungsional</strong></td><td>@include('pdf._status-pill', ['value' => $batch->packingCheck?->primary_functional_test_status])</td></tr>
        @endif
    </tbody>
</table>
@endif
