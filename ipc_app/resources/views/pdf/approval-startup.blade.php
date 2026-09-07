@extends('pdf.layout')

@section('content')
    <div class="section-title">Start Up Inspection Form</div>

    <p style="margin: 0 0 8px; font-size: 9.5px;">
        <strong>Date:</strong> {{ optional($startupCheck?->completed_at ?? $startupCheck?->created_at)->translatedFormat('d/m/Y') ?? '—' }}
    </p>

    @if ($startupCheck)
        {{-- Legacy groups its photos into 3 distinct labeled areas rather than one flat grid:
             IM Number sits alone near the header text, Color + Coding sit together under an
             "Attach Label and Color check actual in this area" heading, and Temperature Setting
             is its own separate section — mirrored here as 3 headed groups (still full-width, not
             squeezed into a narrow column, so every tile stays legible per the prior feedback). --}}
        <div class="photo-groups">
            <div class="photo-group">
                <div class="photo-group-title">IM Number</div>
                <div class="attachment-grid attachment-grid--cols-1">
                    <figure class="attachment-tile">
                        @if ($photoUrls['startup']['im_number'] ?? null)
                            <img src="{{ $photoUrls['startup']['im_number'] }}" alt="IM Number">
                        @else
                            <div class="placeholder">Belum ada foto</div>
                        @endif
                    </figure>
                </div>
            </div>

            <div class="photo-group">
                <div class="photo-group-title">Attach Label and Color Check Actual in This Area</div>
                <div class="attachment-grid attachment-grid--cols-2">
                    @foreach ([['color', 'Color'], ['coding', 'Coding Actual (Primer, Sekunder, Tersier)']] as [$field, $label])
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
            </div>
        </div>

        <div class="photo-group">
            <div class="photo-group-title">Temperature Setting</div>
            {{-- temperature_setting: multi-photo, render one tile per photo --}}
            @php $tempPhotos = $photoUrls['startup']['temperature_setting'] ?? []; @endphp
            <div class="attachment-grid">
                @if (count($tempPhotos) === 0)
                    <figure class="attachment-tile">
                        <div class="placeholder">Belum ada foto</div>
                    </figure>
                @else
                    @foreach ($tempPhotos as $i => $tempUrl)
                        <figure class="attachment-tile">
                            @if ($tempUrl)
                                <img src="{{ $tempUrl }}" alt="Temperature Setting {{ $i + 1 }}">
                            @else
                                <div class="placeholder">Belum ada foto</div>
                            @endif
                            @if (count($tempPhotos) > 1)
                                <figcaption><strong>Foto {{ $i + 1 }}</strong></figcaption>
                            @endif
                        </figure>
                    @endforeach
                @endif
            </div>
        </div>

        {{-- The checklist table still sits beside the sign-off boxes (matching legacy's row 7-9
             arrangement), just without the photos crammed into that same narrow column. --}}
        <div class="startup-layout">
            <div class="startup-main">
                <table>
                    <thead>
                        <tr><th style="width: 5%;">No</th><th>Parameter Pemeriksaan</th><th style="width: 24%;">Hasil</th></tr>
                    </thead>
                    <tbody>
                        @php $no = 1; @endphp
                        @foreach ($startupChecklistGroups as $group)
                            @php
                                $machineFields = collect($group['fields'])->filter(fn ($label, $field) => str_starts_with($field, 'machine_'));
                                $otherFields = collect($group['fields'])->reject(fn ($label, $field) => str_starts_with($field, 'machine_'));
                            @endphp
                            @foreach ($otherFields as $field => $label)
                                <tr>
                                    <td class="center">{{ $no++ }}</td>
                                    <td>{{ $label }}</td>
                                    <td>@include('pdf._status-pill', ['value' => $startupCheck[$field] ?? null])</td>
                                </tr>
                            @endforeach
                            {{-- Legacy nests all 5 machine checks under one "Check detection machine" row
                                 as a sub-table, rather than 5 flat top-level rows. --}}
                            @if ($machineFields->count() > 0)
                                <tr>
                                    <td class="center">{{ $no++ }}</td>
                                    <td>Check Detection Machine</td>
                                    <td class="nested-cell">
                                        <table class="nested-table">
                                            <tbody>
                                                @foreach ($machineFields as $field => $label)
                                                    <tr>
                                                        <td>{{ str_replace('Machine ', '', $label) }}</td>
                                                        <td>@include('pdf._status-pill', ['value' => $startupCheck[$field] ?? null])</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                        <tr>
                            <td class="center">{{ $no++ }}</td>
                            <td>Validation Report (NPD Product)</td>
                            <td>@include('pdf._status-pill', ['value' => $startupCheck->validation_report_status ?? null])</td>
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
            </div>

            <div class="startup-side">
                <div class="sign-grid sign-grid--stack">
                    <div><span>Prepared By</span><small class="role">Operator</small></div>
                    <div><span>Review By</span><small class="role">LL Produksi</small></div>
                    <div><span>Verification By</span><small class="role">IPC</small></div>
                </div>
            </div>
        </div>
    @else
        <p class="muted">Startup Check belum diisi.</p>
    @endif

    <div class="page-break"></div>
    <div class="section-title">Verifikasi Sebelum Produksi</div>

    @if ($startupInspection && ($startupInspection->items->count() > 0 || $startupInspection->samples->count() > 0))
        @php
            $itemsByKey = $startupInspection->items->keyBy('parameter_key');
            $samplesByNo = $startupInspection->samples->keyBy('sample_no');
            $inspectionTime = optional($startupInspection->completed_at ?? $startupInspection->created_at)->translatedFormat('d/m/Y H:i') ?? '—';
            $statusOf = fn (string $key) => $itemsByKey[$key]->status ?? null;
        @endphp

        <table class="record-table">
            <colgroup>
                <col style="width: 4%;">
                <col style="width: 7%;">
                <col style="width: 8%;"><col style="width: 8%;"><col style="width: 9%;"><col style="width: 7%;"><col style="width: 8%;"><col style="width: 8%;">
                <col style="width: 6%;"><col style="width: 6%;"><col style="width: 6%;"><col style="width: 7%;"><col style="width: 7%;"><col style="width: 8%;">
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="2">Sample</th>
                    <th rowspan="2">Time</th>
                    <th colspan="6">Filling</th>
                    <th colspan="6">Packing</th>
                </tr>
                <tr>
                    <th>Warna Bulk / Tekstur</th>
                    <th>Aroma Bulk</th>
                    <th>Tampilan Setelah Filling</th>
                    <th>Volume / Berat</th>
                    <th>Uji Kebocoran</th>
                    <th>Uji Kegunaan</th>
                    <th>Primer</th>
                    <th>Sekunder</th>
                    <th>Tersier</th>
                    <th>Attribute</th>
                    <th>Tampilan</th>
                    <th>Berat M.Box</th>
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
                            <td rowspan="30">@include('pdf._status-pill', ['value' => $statusOf('primer')])</td>
                            <td rowspan="30">@include('pdf._status-pill', ['value' => $statusOf('sekunder')])</td>
                            <td rowspan="30">@include('pdf._status-pill', ['value' => $statusOf('tersier')])</td>
                            <td rowspan="30">@include('pdf._status-pill', ['value' => $statusOf('attribute')])</td>
                            <td rowspan="30">@include('pdf._status-pill', ['value' => $statusOf('appearance')])</td>
                        @endif
                        <td class="center">{{ $samplesByNo[$no]->weight_master_box ?? '—' }}</td>
                    </tr>
                @endfor
            </tbody>
        </table>

        @php $itemRemarks = $startupInspection->items->filter(fn ($i) => filled($i->remark)); @endphp
        @if ($itemRemarks->count() > 0)
            <table>
                <thead>
                    <tr><th style="width: 22%;">Parameter</th><th>Remark</th></tr>
                </thead>
                <tbody>
                    @foreach ($itemRemarks as $item)
                        <tr>
                            <td style="text-transform: capitalize;">{{ str_replace('_', ' ', $item->parameter_key) }}</td>
                            <td>{{ $item->remark }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="section-title" style="margin-top: 4px;">Test Type</div>
        <table>
            <tbody>
                @foreach ($testTypesByCategory as $category => $types)
                    <tr>
                        <td style="width: 16%;"><strong>{{ $category }}</strong></td>
                        <td>
                            @foreach ($types as $t)
                                <span class="status-pill {{ $t['is_performed'] ? 'ok' : 'muted' }}">{{ $t['name'] }}</span>
                            @endforeach
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="muted" style="font-size: 8px;">
            <strong>NOTE:</strong>
            @foreach ($testTypesByCategory as $category => $types)
                {{ strtoupper($category) }} = {{ collect($types)->pluck('name')->implode(', ') }}{{ ! $loop->last ? ' | ' : '' }}
            @endforeach
        </p>

        <div class="sign-grid">
            <div><span>Prepared By</span><small class="role">Line Leader Production</small></div>
            <div><span>Review By</span><small class="role">QC IPC</small></div>
            <div><span>Verification By</span><small class="role">QC Coordinator</small></div>
        </div>
    @else
        <p class="muted">Start Inspection belum diisi (opsional).</p>
    @endif
@endsection
