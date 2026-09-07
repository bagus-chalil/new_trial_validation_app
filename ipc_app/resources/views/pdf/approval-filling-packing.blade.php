@extends('pdf.layout')

@section('content')
    <div class="section-title">A. Filling Inspection</div>

    @if ($fillingCheck)
        <div class="info-grid" style="grid-template-columns: repeat(4, 1fr);">
            <div><span>Date / Shift Filling</span><strong>{{ optional($fillingCheck->completed_at ?? $fillingCheck->updated_at)->translatedFormat('d/m/Y H:i') ?? '—' }}</strong></div>
            <div><span>Machines / Lines</span><strong>{{ $batch->masterLine->name ?? '—' }} ({{ $batch->masterLine->code ?? '—' }})</strong></div>
            <div><span>Density</span><strong>{{ $startupCheck->density ?? '—' }}</strong></div>
            <div><span>TH Progress</span><strong>{{ $fillingCheck->save_count ?? 0 }}</strong></div>
            <div><span>QC Inspector</span><strong>{{ $fillingCheck->user->name ?? '—' }}</strong></div>
            <div style="grid-column: span 3;"><span>Line Leader</span><strong>{{ $startupCheck->line_leader_name ?? '—' }}</strong></div>
        </div>

        @php
            $samplesByNo = $fillingCheck->samples->keyBy('sample_no');
            $results = $samplesByNo->pluck('weight_result')->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v);
        @endphp

        <table>
            <thead>
                <tr>
                    <th rowspan="2" style="width: 14%;">Parameter</th>
                    <th colspan="2">Time: {{ optional($fillingCheck->completed_at ?? $fillingCheck->updated_at)->translatedFormat('H:i') ?? '—' }}</th>
                </tr>
                <tr>
                    <th class="center" style="width: 20%;">Weight Value</th>
                    <th class="center" style="width: 20%;">Weight Result</th>
                </tr>
            </thead>
            <tbody>
                @foreach (range(1, 10) as $no)
                    <tr>
                        <td>Sample {{ $no }}</td>
                        <td class="center">{{ $samplesByNo[$no]->weight_value ?? '—' }}</td>
                        <td class="center">{{ $samplesByNo[$no]->weight_result ?? '—' }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td>Cleaness Bulk & Odor</td>
                    <td colspan="2">@include('pdf._status-pill', ['value' => $fillingCheck->sample_bulk_odor_status])</td>
                </tr>
                <tr>
                    <td>Leakage Test (Vaccum / Press)</td>
                    <td colspan="2">@include('pdf._status-pill', ['value' => $fillingCheck->sample_leakage_test_status])</td>
                </tr>
            </tbody>
        </table>

        <table>
            <tbody>
                <tr>
                    <td style="width: 15%;"><strong>Summary</strong></td>
                    <td style="width: 45%;">
                        Min: {{ $results->isNotEmpty() ? $results->min() : '—' }}
                        &nbsp;&nbsp; Max: {{ $results->isNotEmpty() ? $results->max() : '—' }}
                        &nbsp;&nbsp; Average: {{ $fillingCheck->average_weight ?? ($results->isNotEmpty() ? round($results->avg(), 4) : '—') }}
                    </td>
                    <td style="width: 15%;"><strong>Decision</strong></td>
                    <td>@include('pdf._status-pill', ['value' => $fillingCheck->decision])</td>
                </tr>
                <tr>
                    <td><strong>Remarks</strong></td>
                    <td colspan="3">{{ $fillingCheck->remarks ?? '—' }}</td>
                </tr>
            </tbody>
        </table>

        <div class="attachment-grid" style="grid-template-columns: repeat(4, 1fr);">
            <figure class="attachment-tile">
                @if ($photoUrls['filling']['color'] ?? null)
                    <img src="{{ $photoUrls['filling']['color'] }}" alt="Color">
                @else
                    <div class="placeholder">Belum ada foto</div>
                @endif
                <figcaption><strong>Color</strong></figcaption>
            </figure>
        </div>
    @else
        <p class="muted">Filling Check belum diisi.</p>
    @endif

    @if ($fillingCheck && $fillingCheck->revisions->count() > 0)
        <div class="section-title" style="margin-top: 4px;">Riwayat Simpan — Filling Check (TH Progress)</div>
        <table>
            <thead>
                <tr><th style="width: 6%;">#</th><th style="width: 20%;">Waktu</th><th style="width: 16%;">User</th><th style="width: 10%;">Status</th><th>Ringkasan</th></tr>
            </thead>
            <tbody>
                @foreach ($fillingCheck->revisions->sortByDesc('revision_no') as $rev)
                    <tr>
                        <td class="center">{{ $rev->revision_no }}</td>
                        <td>{{ optional($rev->created_at)->translatedFormat('d M Y H:i') ?? '—' }}</td>
                        <td>{{ $rev->user->name ?? '—' }}</td>
                        <td>{{ $rev->finalize ? 'Selesai' : 'Draft' }}</td>
                        <td>
                            {{ $rev->decision ? 'Decision: '.$rev->decision.'. ' : '' }}
                            {{ $rev->average_weight ? 'Avg Weight: '.$rev->average_weight.'. ' : '' }}
                            {{ $rev->remarks ? 'Remarks: '.$rev->remarks : '' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="page-break"></div>
    <div class="section-title">B. Packing Inspection</div>

    @if ($packingCheck)
        <div class="info-grid" style="grid-template-columns: repeat(4, 1fr);">
            <div><span>Date / Shift Packing</span><strong>{{ optional($packingCheck->completed_at ?? $packingCheck->updated_at)->translatedFormat('d/m/Y H:i') ?? '—' }}</strong></div>
            <div><span>Machines / Lines</span><strong>{{ $batch->masterLine->name ?? '—' }} ({{ $batch->masterLine->code ?? '—' }})</strong></div>
            <div><span>Machines Coding</span><strong>{{ $packingCheck->coding_machine ?? '—' }}</strong></div>
            <div><span>TH Progress</span><strong>{{ $packingCheck->save_count ?? 0 }}</strong></div>
            <div><span>QC</span><strong>{{ $packingCheck->user->name ?? '—' }}</strong></div>
            <div><span>Line Leader</span><strong>{{ $packingCheck->line_leader_name ?? '—' }}</strong></div>
            <div><span>Standar Bruto MB</span><strong>{{ $packingCheck->standard_weight_mb ?? '—' }}</strong></div>
            <div><span>Sum Weight MB</span><strong>{{ $packingCheck->sum_weight_mb ?? '—' }}</strong></div>
        </div>

        @php $itemNo = 0; @endphp
        @foreach ($packingChecklistGroups as $group)
            <table>
                <thead>
                    <tr>
                        <th style="width: 8%;">No</th>
                        <th style="width: 8%;">Kode</th>
                        <th>{{ ucfirst($group['key']) }} Packing</th>
                        <th style="width: 18%;">Hasil</th>
                        <th style="width: 18%;">Foto</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($group['fields'] as $field => $label)
                        @php
                            $itemNo++;
                            $photoField = \App\Models\PackingCheck::PHOTO_FIELD_BY_CHECKLIST_FIELD[$field] ?? null;
                            $photoUrl = $photoField ? ($photoUrls['packing'][$photoField] ?? null) : null;
                        @endphp
                        <tr>
                            <td class="center">{{ $itemNo }}</td>
                            <td class="center">{{ \App\Models\PackingCheck::SEVERITY_LABELS[$field] ?? '—' }}</td>
                            <td>{{ $label }}</td>
                            <td>@include('pdf._status-pill', ['value' => $packingCheck[$field] ?? null])</td>
                            <td class="center">
                                @if ($photoField)
                                    @if ($photoUrl)
                                        <img src="{{ $photoUrl }}" alt="{{ $label }}" style="width: 100%; max-height: 20mm; object-fit: contain;">
                                    @else
                                        <span class="muted">Belum ada foto</span>
                                    @endif
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach

        <table>
            <tbody>
                <tr>
                    <td style="width: 15%;"><strong>Decision</strong></td>
                    <td style="width: 20%;">@include('pdf._status-pill', ['value' => $packingCheck->decision])</td>
                    <td style="width: 15%;"><strong>Remarks</strong></td>
                    <td>{{ $packingCheck->remarks ?? '—' }}</td>
                </tr>
            </tbody>
        </table>

        <div class="attachment-grid" style="grid-template-columns: repeat(2, 1fr);">
            @foreach ([
                ['palletisasi', 'Palletisasi'],
                ['color', 'Color'],
            ] as [$field, $label])
                <figure class="attachment-tile">
                    @if ($photoUrls['packing'][$field] ?? null)
                        <img src="{{ $photoUrls['packing'][$field] }}" alt="{{ $label }}">
                    @else
                        <div class="placeholder">Belum ada foto</div>
                    @endif
                    <figcaption><strong>{{ $label }}</strong></figcaption>
                </figure>
            @endforeach
        </div>
    @else
        <p class="muted">Packing Check belum diisi.</p>
    @endif

    @if ($packingCheck && $packingCheck->revisions->count() > 0)
        <div class="section-title" style="margin-top: 4px;">Riwayat Simpan — Packing Check (TH Progress)</div>
        <table>
            <thead>
                <tr><th style="width: 6%;">#</th><th style="width: 20%;">Waktu</th><th style="width: 16%;">User</th><th style="width: 10%;">Status</th><th>Ringkasan</th></tr>
            </thead>
            <tbody>
                @foreach ($packingCheck->revisions->sortByDesc('revision_no') as $rev)
                    <tr>
                        <td class="center">{{ $rev->revision_no }}</td>
                        <td>{{ optional($rev->created_at)->translatedFormat('d M Y H:i') ?? '—' }}</td>
                        <td>{{ $rev->user->name ?? '—' }}</td>
                        <td>{{ $rev->finalize ? 'Selesai' : 'Draft' }}</td>
                        <td>
                            {{ $rev->decision ? 'Decision: '.$rev->decision.'. ' : '' }}
                            {{ $rev->sum_weight_mb ? 'Sum Weight MB: '.$rev->sum_weight_mb.'. ' : '' }}
                            {{ $rev->remarks ? 'Remarks: '.$rev->remarks : '' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="muted" style="margin-top: 6px;">ZD = Zero Defect &nbsp; C = Critical Defect &nbsp; M = Major Defect &nbsp; m = Minor Defect</p>

    <div class="sign-grid">
        <div><span>Issued By (QC Filling / Packing)</span></div>
        <div><span>Review By (QC IPC Coordinator)</span></div>
    </div>
@endsection
