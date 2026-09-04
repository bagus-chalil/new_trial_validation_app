@extends('pdf.layout')

@section('content')
    <div class="section-title">A. Filling Inspection</div>

    @if ($fillingCheck)
        <div class="two-col">
            <table>
                <tbody>
                    <tr><td><strong>QC Inspector</strong></td><td>{{ $fillingCheck->user->name ?? '—' }}</td></tr>
                    <tr><td><strong>Line Leader</strong></td><td>{{ $startupCheck->line_leader_name ?? '—' }}</td></tr>
                </tbody>
            </table>
            <table>
                <tbody>
                    <tr><td><strong>Density</strong></td><td>{{ $startupCheck->density ?? '—' }}</td></tr>
                    <tr><td><strong>Average Weight (Result)</strong></td><td>{{ $fillingCheck->average_weight ?? '—' }}</td></tr>
                </tbody>
            </table>
        </div>

        <table>
            <thead>
                <tr><th style="width: 8%;">Sample</th><th>Weight Value</th><th>Weight Result</th></tr>
            </thead>
            <tbody>
                @php $samplesByNo = $fillingCheck->samples->keyBy('sample_no'); @endphp
                @foreach (range(1, 10) as $no)
                    <tr>
                        <td class="center">{{ $no }}</td>
                        <td>{{ $samplesByNo[$no]->weight_value ?? '—' }}</td>
                        <td>{{ $samplesByNo[$no]->weight_result ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table>
            <tbody>
                <tr>
                    <td style="width: 25%;"><strong>Kebersihan Bulk & Odor</strong></td>
                    <td style="width: 25%;">@include('pdf._status-pill', ['value' => $fillingCheck->sample_bulk_odor_status])</td>
                    <td style="width: 25%;"><strong>Uji Kebocoran</strong></td>
                    <td>@include('pdf._status-pill', ['value' => $fillingCheck->sample_leakage_test_status])</td>
                </tr>
                <tr>
                    <td><strong>Decision</strong></td>
                    <td>@include('pdf._status-pill', ['value' => $fillingCheck->decision])</td>
                    <td colspan="2"><strong>Remarks:</strong> {{ $fillingCheck->remarks ?? '—' }}</td>
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
        <div class="two-col">
            <table>
                <tbody>
                    <tr><td><strong>QC</strong></td><td>{{ $packingCheck->user->name ?? '—' }}</td></tr>
                    <tr><td><strong>Line Leader</strong></td><td>{{ $packingCheck->line_leader_name ?? '—' }}</td></tr>
                </tbody>
            </table>
            <table>
                <tbody>
                    <tr><td><strong>Machines Coding</strong></td><td>{{ $packingCheck->coding_machine ?? '—' }}</td></tr>
                    <tr><td><strong>Std / Sum Weight MB</strong></td><td>{{ $packingCheck->standard_weight_mb ?? '—' }} / {{ $packingCheck->sum_weight_mb ?? '—' }}</td></tr>
                </tbody>
            </table>
        </div>

        @foreach ($packingChecklistGroups as $group)
            <table>
                <thead>
                    <tr><th colspan="2">{{ ucfirst($group['key']) }} Packing</th></tr>
                </thead>
                <tbody>
                    @foreach ($group['fields'] as $field => $label)
                        <tr>
                            <td style="width: 70%;">{{ $label }}</td>
                            <td>@include('pdf._status-pill', ['value' => $packingCheck[$field] ?? null])</td>
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

        <div class="attachment-grid">
            @foreach ([
                ['palletisasi', 'Palletisasi'],
                ['color', 'Color'],
                ['primary_coding_batch_exp', 'Primary Coding Batch/EXP'],
                ['secondary_coding_batch_exp', 'Secondary Coding'],
                ['tersier_coding_batch', 'Tersier Coding / Shipper'],
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

    <div class="sign-grid">
        <div><span>Issued By (QC Filling / Packing)</span></div>
        <div><span>Review By (QC IPC Coordinator)</span></div>
    </div>
@endsection
