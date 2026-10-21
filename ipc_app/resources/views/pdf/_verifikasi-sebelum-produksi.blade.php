{{--
    "VERIFIKASI SEBELUM PRODUKSI" — Filling side only (paper form FR.QAC.192.02's 6-parameter ×
    Jam/Sample-1..30 grid). Orientation matches the real paper form exactly: PARAMETER is the row
    axis, SAMPEL 1-30 is the column axis (the app's first build had this transposed — sample as
    rows, parameter as columns — a real layout bug fixed 2026-10-21 per direct user comparison
    against the real form). Four of the 6 parameters (Warna Bulk/Tekstur, Aroma Bulk, Tampilan
    Setelah Filling, Uji Kebocoran, Uji Kegunaan) are captured as ONE status for the whole batch
    (StartupInspectionItem), not per sample, so they render as a single colspan=30 cell — the
    honest shape of the data this app captures. Only Volume/Berat has a genuine per-sample reading
    (StartupInspectionSample) and gets its own value per column. This transposition also collapses
    the grid from 30 rows to 6, which is most of why this section used to spill across two pages.

    Expects $startupInspection in scope (added to fillingPackingPayload() for the Filling side).
--}}
@if ($startupInspection && ($startupInspection->items->count() > 0 || $startupInspection->samples->count() > 0))
    @php
        $itemsByKey = $startupInspection->items->keyBy('parameter_key');
        $samplesByNo = $startupInspection->samples->keyBy('sample_no');
        $inspectionTime = optional($startupInspection->completed_at ?? $startupInspection->created_at)->translatedFormat('d/m/Y H:i') ?? '—';
        $statusOf = fn (string $key) => $itemsByKey[$key]->status ?? null;
        // Trims trailing zeros (15.00 -> 15, 15.60 -> 15.6) so a value fits one of 30 very narrow
        // columns on an A4 page without wrapping.
        $trimVal = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
        $singleValueRows = [
            'bulk_color_texture' => 'Warna Bulk / Tekstur',
            'bulk_odor' => 'Aroma Bulk',
            'appearance_after_filling' => 'Tampilan Setelah Filling',
        ];
    @endphp

    {{-- record-table--compact: tighter padding/font so the grid stays short. --}}
    <table class="record-table record-table--compact">
        <colgroup>
            <col style="width: 10%;">
            <col style="width: 6%;">
            @for ($i = 0; $i < 30; $i++)
                <col style="width: {{ 84 / 30 }}%;">
            @endfor
        </colgroup>
        <thead>
            <tr>
                <th rowspan="2">Parameter</th>
                <th rowspan="2">Jam</th>
                <th colspan="30">Sampel</th>
            </tr>
            <tr>
                @for ($i = 1; $i <= 30; $i++)
                    <th class="center" style="padding: 1px;">{{ $i }}</th>
                @endfor
            </tr>
        </thead>
        <tbody>
            @foreach ($singleValueRows as $key => $label)
                <tr>
                    <td>{{ $label }}</td>
                    @if ($loop->first)
                        <td class="center" rowspan="{{ count($singleValueRows) + 3 }}">{{ $inspectionTime }}</td>
                    @endif
                    <td colspan="30" class="center">@include('pdf._status-pill', ['value' => $statusOf($key), 'abbreviate' => true])</td>
                </tr>
            @endforeach
            <tr>
                <td>Volume / Berat</td>
                @for ($no = 1; $no <= 30; $no++)
                    <td class="center" style="padding: 1px; font-size: 6.5px;">{{ $trimVal($samplesByNo[$no]->volume_weight ?? null) }}</td>
                @endfor
            </tr>
            <tr>
                <td>Uji Kebocoran</td>
                <td colspan="30" class="center">@include('pdf._status-pill', ['value' => $statusOf('leakage_test'), 'abbreviate' => true])</td>
            </tr>
            <tr>
                <td>Uji Kegunaan</td>
                <td colspan="30" class="center">@include('pdf._status-pill', ['value' => $statusOf('functional_test'), 'abbreviate' => true])</td>
            </tr>
        </tbody>
    </table>

    @php $itemRemarks = $startupInspection->items->filter(fn ($i) => filled($i->remark)); @endphp
    @if ($itemRemarks->count() > 0)
        @php
            $parameterLabels = [
                'bulk_color_texture' => 'Warna Bulk / Tekstur',
                'bulk_odor' => 'Aroma Bulk',
                'appearance_after_filling' => 'Tampilan Setelah Filling',
                'leakage_test' => 'Uji Kebocoran',
                'functional_test' => 'Uji Kegunaan',
            ];
        @endphp
        <table>
            <thead>
                <tr><th style="width: 22%;">Parameter</th><th>Remark</th></tr>
            </thead>
            <tbody>
                @foreach ($itemRemarks as $item)
                    @continue (! isset($parameterLabels[$item->parameter_key]))
                    <tr>
                        <td>{{ $parameterLabels[$item->parameter_key] }}</td>
                        <td>{{ $item->remark }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@else
    <p class="muted">Start Inspection belum diisi (opsional).</p>
@endif
