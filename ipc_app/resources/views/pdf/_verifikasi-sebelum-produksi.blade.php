{{--
    "VERIFIKASI SEBELUM PRODUKSI" — Filling side only (paper form FR.QAC.192.02's 6-parameter ×
    Jam/Sample-1..30 grid). Four of the 6 parameters (Warna Bulk/Tekstur, Aroma Bulk, Tampilan
    Setelah Filling, Uji Kebocoran, Uji Kegunaan) are captured as ONE status for the whole batch
    (StartupInspectionItem), not per sample, so they render as a single rowspan=30 cell — the
    honest shape of the data this app captures, same pattern as approval-startup.blade.php's own
    copy of this grid. Only Volume/Berat has a genuine per-sample reading (StartupInspectionSample)
    and gets its own value per row.

    Expects $startupInspection in scope (added to fillingPackingPayload() for the Filling side).
--}}
@if ($startupInspection && ($startupInspection->items->count() > 0 || $startupInspection->samples->count() > 0))
    @php
        $itemsByKey = $startupInspection->items->keyBy('parameter_key');
        $samplesByNo = $startupInspection->samples->keyBy('sample_no');
        $inspectionTime = optional($startupInspection->completed_at ?? $startupInspection->created_at)->translatedFormat('d/m/Y H:i') ?? '—';
        $statusOf = fn (string $key) => $itemsByKey[$key]->status ?? null;
    @endphp

    {{-- record-table--compact: tighter padding/font so 30 fixed rows don't push the rest of this
         report onto an extra page (see layout.blade.php's definition). --}}
    <table class="record-table record-table--compact">
        <colgroup>
            <col style="width: 6%;">
            <col style="width: 10%;">
            <col style="width: 14%;"><col style="width: 14%;"><col style="width: 16%;"><col style="width: 12%;"><col style="width: 14%;"><col style="width: 14%;">
        </colgroup>
        <thead>
            <tr>
                <th rowspan="2">Sample</th>
                <th rowspan="2">Jam</th>
                <th colspan="6">Parameter</th>
            </tr>
            <tr>
                <th>Warna Bulk / Tekstur</th>
                <th>Aroma Bulk</th>
                <th>Tampilan Setelah Filling</th>
                <th>Volume / Berat</th>
                <th>Uji Kebocoran</th>
                <th>Uji Kegunaan</th>
            </tr>
        </thead>
        <tbody>
            @for ($no = 1; $no <= 30; $no++)
                <tr>
                    <td class="center">{{ $no }}</td>
                    @if ($no === 1)
                        <td class="center" rowspan="30">{{ $inspectionTime }}</td>
                        <td rowspan="30">@include('pdf._status-pill', ['value' => $statusOf('bulk_color_texture')])</td>
                        <td rowspan="30">@include('pdf._status-pill', ['value' => $statusOf('bulk_odor')])</td>
                        <td rowspan="30">@include('pdf._status-pill', ['value' => $statusOf('appearance_after_filling')])</td>
                    @endif
                    <td class="center">{{ $samplesByNo[$no]->volume_weight ?? '—' }}</td>
                    @if ($no === 1)
                        <td rowspan="30">@include('pdf._status-pill', ['value' => $statusOf('leakage_test')])</td>
                        <td rowspan="30">@include('pdf._status-pill', ['value' => $statusOf('functional_test')])</td>
                    @endif
                </tr>
            @endfor
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
