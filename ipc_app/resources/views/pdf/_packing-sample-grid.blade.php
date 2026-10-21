{{--
    One "PEMERIKSAAN BERAT PRODUK" row (paper form FR.QAC.182.03) — a label cell spanning 3 rows
    beside a 10-column × 3-row sample-number grid (reading column-major: col 1 = samples 1/2/3,
    col 2 = samples 4/5/6, ... col 10 = samples 28/29/30 — matches the real paper form's own
    numbering, not a row-major 1..30 sequence).

    Params: $label (e.g. "Berat Filling"), $samplesByNo (collection keyed by sample_no, from
    StartupInspectionSample — see _verifikasi-sebelum-produksi.blade.php for why this is the real
    underlying data, not fabricated per-packing-check capture), $field (the attribute to read off
    each sample, e.g. "volume_weight" or "weight_master_box").
--}}
<table class="record-table">
    <colgroup>
        <col style="width: 10%;">
        @for ($col = 0; $col < 10; $col++)
            <col style="width: 9%;">
        @endfor
    </colgroup>
    <tbody>
        @for ($row = 0; $row < 3; $row++)
            <tr>
                @if ($row === 0)
                    <td rowspan="3"><strong>{{ $label }}</strong></td>
                @endif
                @for ($col = 0; $col < 10; $col++)
                    @php $sampleNo = $col * 3 + $row + 1; @endphp
                    <td class="center">{{ $samplesByNo[$sampleNo]->{$field} ?? '—' }}</td>
                @endfor
            </tr>
        @endfor
    </tbody>
</table>
