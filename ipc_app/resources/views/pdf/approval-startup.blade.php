@extends('pdf.layout')

@section('content')
    <div class="section-title">Start Up Inspection Form</div>

    <div class="attachment-grid">
        @foreach ([['im_number', 'IM Number'], ['color', 'Color'], ['temperature_setting', 'Temperature Setting']] as [$field, $label])
            <figure class="attachment-tile">
                @if ($photoUrls['startup'][$field] ?? null)
                    <img src="{{ $photoUrls['startup'][$field] }}" alt="{{ $label }}">
                @else
                    <div class="placeholder">Belum ada foto</div>
                @endif
                <figcaption><strong>{{ $label }}</strong></figcaption>
            </figure>
        @endforeach
    </div>

    @if ($startupCheck)
        <table>
            <thead>
                <tr><th style="width: 4%;">No</th><th>Parameter Pemeriksaan</th><th style="width: 22%;">Hasil</th></tr>
            </thead>
            <tbody>
                @php $no = 1; @endphp
                @foreach ($startupChecklistGroups as $group)
                    @foreach ($group['fields'] as $field => $label)
                        <tr>
                            <td class="center">{{ $no++ }}</td>
                            <td>{{ $label }}</td>
                            <td>@include('pdf._status-pill', ['value' => $startupCheck[$field] ?? null])</td>
                        </tr>
                    @endforeach
                @endforeach
                <tr>
                    <td class="center">{{ $no++ }}</td>
                    <td>Validation Report (NPD Product)</td>
                    <td>@include('pdf._status-pill', ['value' => $startupCheck->validation_report_status ?? null])</td>
                </tr>
                <tr>
                    <td class="center">{{ $no++ }}</td>
                    <td>Identity Board Line</td>
                    <td>@include('pdf._status-pill', ['value' => $startupCheck->identity_line_board_status ?? null])</td>
                </tr>
            </tbody>
        </table>

        <div class="two-col">
            <table>
                <tbody>
                    <tr><td><strong>Filling Range Min</strong></td><td>{{ $startupCheck->filling_range_min ?? '—' }}</td></tr>
                    <tr><td><strong>Filling Range Max</strong></td><td>{{ $startupCheck->filling_range_max ?? '—' }}</td></tr>
                    <tr><td><strong>Density</strong></td><td>{{ $startupCheck->density ?? '—' }}</td></tr>
                    <tr><td><strong>Avg. Empty Bottle Weight</strong></td><td>{{ $startupCheck->average_of_empty_bottle_weight ?? '—' }}</td></tr>
                </tbody>
            </table>
            <table>
                <tbody>
                    <tr><td><strong>Heating</strong></td><td>{{ $startupCheck->heating ?? '—' }}</td></tr>
                    <tr><td><strong>Line Leader</strong></td><td>{{ $startupCheck->line_leader_name ?? '—' }}</td></tr>
                    <tr><td><strong>Operator</strong></td><td>{{ $startupCheck->operator_name ?? '—' }}</td></tr>
                    <tr><td><strong>Prepared By</strong></td><td>{{ $startupCheck->user->name ?? '—' }}</td></tr>
                </tbody>
            </table>
        </div>

        <table>
            <tbody>
                <tr><td style="width: 12%;"><strong>Remarks</strong></td><td>{{ $startupCheck->remarks ?? '—' }}</td></tr>
            </tbody>
        </table>

        <div class="sign-grid">
            <div><span>Prepared By</span></div>
            <div><span>Review By</span></div>
            <div><span>Verification By</span></div>
        </div>
    @else
        <p class="muted">Startup Check belum diisi.</p>
    @endif

    @if ($startupInspection && ($startupInspection->items->count() > 0 || $startupInspection->samples->count() > 0))
        <div class="page-break"></div>
        <div class="section-title">Verifikasi Sebelum Produksi</div>

        @php
            $itemsByKey = $startupInspection->items->keyBy('parameter_key');
            $samplesByNo = $startupInspection->samples->keyBy('sample_no');
        @endphp

        <table>
            <thead>
                <tr><th>Parameter</th><th style="width: 18%;">Status</th><th>Remark</th></tr>
            </thead>
            <tbody>
                @foreach ($startupInspectionParameterKeys as $key)
                    <tr>
                        <td style="text-transform: capitalize;">{{ str_replace('_', ' ', $key) }}</td>
                        <td>@include('pdf._status-pill', ['value' => $itemsByKey[$key]->status ?? null])</td>
                        <td>{{ $itemsByKey[$key]->remark ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table>
            <thead>
                <tr><th style="width: 8%;">Sample</th><th>Volume / Weight</th><th>Weight M.Box</th></tr>
            </thead>
            <tbody>
                @forelse (range(1, 30) as $no)
                    @continue(! $samplesByNo->has($no))
                    <tr>
                        <td class="center">{{ $no }}</td>
                        <td>{{ $samplesByNo[$no]->volume_weight ?? '—' }}</td>
                        <td>{{ $samplesByNo[$no]->weight_master_box ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">Belum ada sample diisi.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="section-title" style="margin-top: 4px;">Test Type</div>
        @foreach ($testTypesByCategory as $category => $types)
            <p><strong>{{ $category }}:</strong>
                @foreach ($types as $t)
                    <span class="status-pill {{ $t['is_performed'] ? 'ok' : 'muted' }}">{{ $t['name'] }}</span>
                @endforeach
            </p>
        @endforeach

        <div class="sign-grid">
            <div><span>Line Leader Production</span></div>
            <div><span>QC IPC</span></div>
            <div><span>QC Coordinator</span></div>
        </div>
    @endif
@endsection
