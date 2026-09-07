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
            // One column per TH_PROGRESS round (every draft/finalize save creates a
            // FillingCheckRevision snapshot — see SaveFillingCheck::handle()), matching the
            // legacy "In Process Control Inspection Report" form's repeating Time columns
            // instead of collapsing to only the latest save. A check saved before revision
            // tracking existed (or a stray direct DB write) falls back to one column built
            // from the live row so the table never renders empty.
            $fillingRounds = $fillingCheck->revisions->sortBy('revision_no')->values();
            if ($fillingRounds->isEmpty()) {
                $fillingRounds = collect([$fillingCheck]);
            }
            $results = $fillingCheck->samples->pluck('weight_result')->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v);
            $parameterWidth = 14;
            $fillingTimeColWidth = (100 - $parameterWidth) / max($fillingRounds->count(), 1);
        @endphp

        <table class="record-table">
            <colgroup>
                <col style="width: {{ $parameterWidth }}%;">
                @foreach ($fillingRounds as $round)
                    <col style="width: {{ $fillingTimeColWidth }}%;">
                @endforeach
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="2">Parameter</th>
                    <th colspan="{{ $fillingRounds->count() }}">Time</th>
                </tr>
                <tr>
                    @foreach ($fillingRounds as $round)
                        <th class="center">{{ optional($round->created_at)->format('H:i') ?? '—' }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach (range(1, 10) as $no)
                    <tr>
                        <td>Sample {{ $no }}</td>
                        @foreach ($fillingRounds as $round)
                            <td class="center">{{ $round->samples->firstWhere('sample_no', $no)->weight_value ?? '—' }}</td>
                        @endforeach
                    </tr>
                @endforeach
                <tr>
                    <td>Cleaness Bulk & Odor</td>
                    @foreach ($fillingRounds as $round)
                        <td class="center">@include('pdf._status-pill', ['value' => $round->sample_bulk_odor_status, 'abbreviate' => true])</td>
                    @endforeach
                </tr>
                <tr>
                    <td>Leakage Test (Vaccum / Press)</td>
                    @foreach ($fillingRounds as $round)
                        <td class="center">@include('pdf._status-pill', ['value' => $round->sample_leakage_test_status, 'abbreviate' => true])</td>
                    @endforeach
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

        @php
            // One column per TH_PROGRESS round, same rationale as Filling above — every draft
            // save snapshots the full checklist into a PackingCheckRevision and then blanks the
            // live row for the next round (see SavePackingCheck::handle()), so $packingCheck
            // itself only ever holds the *last* round's answers. Photos are NOT versioned per
            // round (IpcAttachment overwrites the previous file on re-upload — see
            // PackingCheckController::uploadPhoto()), so only the current photo is shown, once,
            // rather than fabricating a different image per column the way the legacy paper form
            // does.
            $packingRounds = $packingCheck->revisions->sortBy('revision_no')->values();
            if ($packingRounds->isEmpty()) {
                $packingRounds = collect([$packingCheck]);
            }
        @endphp
        @php
            // One continuous table for all three tiers (was one <table> per tier) so the column
            // grid lines run straight top-to-bottom instead of restarting — and each column's
            // width is fixed via colgroup + table-layout: fixed (see .record-table in
            // pdf/layout.blade.php) rather than left to the browser to re-guess per row.
            $noWidth = 6;
            $kodeWidth = 6;
            $itemWidth = 24;
            $photoWidth = 16;
            $roundCount = max($packingRounds->count(), 1);
            $timeColWidth = (100 - $noWidth - $kodeWidth - $itemWidth - $photoWidth) / $roundCount;
            $photoColWidth = $photoWidth / $roundCount;
            $itemNo = 0;
        @endphp
        <table class="record-table">
            <colgroup>
                <col style="width: {{ $noWidth }}%;">
                <col style="width: {{ $kodeWidth }}%;">
                <col style="width: {{ $itemWidth }}%;">
                @foreach ($packingRounds as $round)
                    <col style="width: {{ $timeColWidth }}%;">
                @endforeach
                @foreach ($packingRounds as $round)
                    <col style="width: {{ $photoColWidth }}%;">
                @endforeach
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="2">No</th>
                    <th rowspan="2">Kode</th>
                    <th rowspan="2">Item</th>
                    <th colspan="{{ $packingRounds->count() }}">Time</th>
                    <th colspan="{{ $packingRounds->count() }}">Foto</th>
                </tr>
                <tr>
                    @foreach ($packingRounds as $round)
                        <th class="center">{{ optional($round->created_at)->format('H:i') ?? '—' }}</th>
                    @endforeach
                    @foreach ($packingRounds as $round)
                        <th>&nbsp;</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($packingChecklistGroups as $group)
                    <tr class="group-row">
                        <td colspan="{{ 3 + ($packingRounds->count() * 2) }}">{{ ucfirst($group['key']) }} Packing</td>
                    </tr>
                    @foreach ($group['fields'] as $field => $label)
                        @php
                            $itemNo++;
                            $photoField = \App\Models\PackingCheck::PHOTO_FIELD_BY_CHECKLIST_FIELD[$field] ?? null;
                        @endphp
                        <tr>
                            <td class="center">{{ $itemNo }}</td>
                            <td class="center">{{ \App\Models\PackingCheck::SEVERITY_LABELS[$field] ?? '—' }}</td>
                            <td>{{ $label }}</td>
                            @foreach ($packingRounds as $round)
                                <td class="center">@include('pdf._status-pill', ['value' => $round[$field] ?? null, 'abbreviate' => true])</td>
                            @endforeach
                            @foreach ($packingRounds as $round)
                                @php
                                    // Each round shows the photo that was actually current when
                                    // *that* round was saved (PackingCheckRevisionPhoto), not
                                    // whichever upload happens to be latest by print time — a
                                    // fallback (no-revisions) row has no per-round photo history,
                                    // so it uses the single current photoUrls value instead.
                                    $roundPhotoUrl = $photoField
                                        ? ($round instanceof \App\Models\PackingCheckRevision
                                            ? ($packingRevisionPhotoUris[$round->id][$photoField] ?? null)
                                            : ($photoUrls['packing'][$photoField] ?? null))
                                        : null;
                                @endphp
                                <td class="center photo-cell">
                                    @if ($photoField)
                                        @if ($roundPhotoUrl)
                                            <img src="{{ $roundPhotoUrl }}" alt="{{ $label }}" style="width: 100%; max-height: 18mm; object-fit: contain;">
                                        @else
                                            <span class="muted">Belum ada foto</span>
                                        @endif
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>

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

    <p class="muted" style="margin-top: 6px;">CF = Conform &nbsp; NC = Not Conform &nbsp; N/A = Not Applicable &nbsp;&nbsp;|&nbsp;&nbsp; ZD = Zero Defect &nbsp; C = Critical Defect &nbsp; M = Major Defect &nbsp; m = Minor Defect</p>

    <div class="sign-grid">
        <div><span>Issued By (QC Filling / Packing)</span></div>
        <div><span>Review By (QC IPC Coordinator)</span></div>
    </div>
@endsection
