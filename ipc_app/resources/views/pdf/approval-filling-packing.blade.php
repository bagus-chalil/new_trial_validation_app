@extends('pdf.layout')

@section('content')
    <div class="section-title">A. Filling Inspection</div>

    @if ($fillingCheck)
        <div class="info-grid" style="grid-template-columns: repeat(4, 1fr);">
            <div><span>Date / Shift Filling</span><strong>{{ optional($fillingCheck->completed_at ?? $fillingCheck->updated_at)->translatedFormat('d/m/Y H:i') ?? '—' }}</strong></div>
            <div><span>Machines / Lines</span><strong>{{ $batch->masterLine->name ?? '—' }} ({{ $batch->masterLine->code ?? '—' }})</strong></div>
            <div><span>Density</span><strong>{{ $startupCheck?->density_not_applicable ? 'N/A' : ($startupCheck?->density ?? '—') }}</strong></div>
            <div><span>TH Progress</span><strong>{{ $fillingCheck->save_count ?? 0 }}</strong></div>
            <div><span>QC Inspector</span><strong>{{ $fillingCheck->user->name ?? '—' }}</strong></div>
            <div><span>Line Leader</span><strong>{{ $startupCheck->line_leader_name ?? '—' }}</strong></div>
            <div><span>Min Volume</span><strong>{{ $startupCheck->filling_range_min ?? '—' }}</strong></div>
            <div><span>Max Weight</span><strong>{{ $startupCheck->filling_range_max ?? '—' }}</strong></div>
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
            // Summary covers every round shown in the grid, not only the last (live) one.
            $results = $fillingRounds->flatMap(fn ($round) => $round->samples)->pluck('weight_value')->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v);
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
                        <th class="center">{{ optional($round->created_at)->format('d/m H:i') ?? '—' }}</th>
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
                    <td style="width: 15%;"><strong>Summary (semua TH Progress)</strong></td>
                    <td style="width: 45%;">
                        Min: {{ $results->isNotEmpty() ? number_format($results->min(), 2) : '—' }}
                        &nbsp;&nbsp; Max: {{ $results->isNotEmpty() ? number_format($results->max(), 2) : '—' }}
                        &nbsp;&nbsp; Average: {{ $results->isNotEmpty() ? number_format($results->avg(), 2) : '—' }}
                    </td>
                    <td style="width: 15%;"><strong>Decision</strong></td>
                    <td>@include('pdf._status-pill', ['value' => $fillingCheck->decision])</td>
                </tr>
                <tr>
                    <td><strong>Remarks</strong></td>
                    <td colspan="3" style="white-space: pre-line;">{{ $fillingCheck->remarks ?? '—' }}</td>
                </tr>
            </tbody>
        </table>

        <div class="attachment-grid" style="grid-template-columns: repeat(4, 1fr);">
            @foreach (\App\Models\FillingCheck::PHOTO_LABELS as $field => $label)
                <figure class="attachment-tile">
                    @if ($photoUrls['filling'][$field] ?? null)
                        <img src="{{ $photoUrls['filling'][$field] }}" alt="{{ $label }}">
                    @else
                        <div class="placeholder">Belum ada foto</div>
                    @endif
                    <figcaption><strong>{{ $label }}</strong></figcaption>
                </figure>
            @endforeach
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
                            {{ $rev->samples->whereNotNull('weight_value')->isNotEmpty() ? 'Avg Weight: '.$rev->average_weight.'. ' : '' }}
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
            <div><span>Machines / Lines</span><strong>{{ ($packingCheck->masterLine ?? $batch->masterLine)->name ?? '—' }} ({{ ($packingCheck->masterLine ?? $batch->masterLine)->code ?? '—' }})</strong></div>
            <div><span>Machines Coding</span><strong>{{ $packingCheck->coding_machine ?? '—' }}</strong></div>
            <div><span>TH Progress</span><strong>{{ $packingCheck->save_count ?? 0 }}</strong></div>
            <div><span>QC</span><strong>{{ $packingCheck->user->name ?? '—' }}</strong></div>
            <div><span>Line Leader</span><strong>{{ $packingCheck->line_leader_name ?? '—' }}</strong></div>
            <div><span>Std Bruto MB</span><strong>{{ $packingCheck->standard_weight_mb ?? '—' }}</strong></div>
            <div><span>Data Timbang</span><strong>{{ $packingCheck->weighing_data ?? '—' }}</strong></div>
        </div>

        @php
            // One column per TH_PROGRESS round, same rationale as Filling above — every draft
            // save snapshots the full checklist into a PackingCheckRevision and then blanks the
            // live row for the next round (see SavePackingCheck::handle()), so $packingCheck
            // itself only ever holds the *last* round's answers. Photos are snapshotted per round
            // too (PackingCheckRevisionPhoto), so the Foto columns show each round's own photo.
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
            $roundCount = max($packingRounds->count(), 1);
            $timeColWidth = (100 - $noWidth - $kodeWidth - $itemWidth) / $roundCount;
        @endphp
        <table class="record-table">
            <colgroup>
                <col style="width: {{ $noWidth }}%;">
                <col style="width: {{ $kodeWidth }}%;">
                <col style="width: {{ $itemWidth }}%;">
                @foreach ($packingRounds as $round)
                    <col style="width: {{ $timeColWidth }}%;">
                @endforeach
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="2">No</th>
                    <th rowspan="2">Kode</th>
                    <th rowspan="2">Parameter</th>
                    <th colspan="{{ $packingRounds->count() }}">Time</th>
                </tr>
                <tr>
                    @foreach ($packingRounds as $round)
                        <th class="center">{{ optional($round->created_at)->format('d/m H:i') ?? '—' }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                {{-- Line per round: Packing can switch lines between TH_PROGRESS rounds. Rounds
                     saved before per-round lines were recorded fall back to the current line. --}}
                <tr>
                    <td colspan="3"><strong>Machines / Lines</strong></td>
                    @foreach ($packingRounds as $round)
                        @php $roundLine = $round->masterLine ?? $packingCheck->masterLine ?? $batch->masterLine; @endphp
                        <td class="center">{{ $roundLine->code ?? '—' }}</td>
                    @endforeach
                </tr>
                @foreach ($packingChecklistGroups as $group)
                    <tr class="group-row">
                        <td colspan="{{ 3 + $packingRounds->count() }}">{{ ucfirst($group['key']) }} Packaging</td>
                    </tr>
                    {{-- Numbered per tier (1..n restarting under each heading), like the paper form. --}}
                    @foreach (array_keys($group['fields']) as $index => $field)
                        @php
                            $label = $group['fields'][$field];
                            $itemNo = $index + 1;
                            $photoField = \App\Models\PackingCheck::PHOTO_FIELD_BY_CHECKLIST_FIELD[$field] ?? null;
                        @endphp
                        <tr>
                            <td class="center">{{ $itemNo }}</td>
                            <td class="center">{{ \App\Models\PackingCheck::SEVERITY_LABELS[$field] ?? '—' }}</td>
                            <td>{{ $label }}</td>
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
                                {{-- Status and (for checklist rows that have one) the round's photo share one
                                     cell, so there is no separate, mostly-empty Foto column. --}}
                                <td class="center photo-cell">
                                    @include('pdf._status-pill', ['value' => $round[$field] ?? null, 'abbreviate' => true])
                                    @if ($roundPhotoUrl)
                                        <img src="{{ $roundPhotoUrl }}" alt="{{ $label }}" style="width: 100%; max-height: 18mm; object-fit: contain; margin-top: 3px;">
                                    @elseif ($photoField)
                                        <div class="muted" style="margin-top: 2px;">Belum ada foto</div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    @if ($group['key'] === 'tersier')
                        {{-- Tersier's last row on the paper form. Weighed every TH_PROGRESS round
                             (sum_weight_mb column), so it gets one value per round column. --}}
                        <tr>
                            <td class="center">{{ count($group['fields']) + 1 }}</td>
                            <td class="center">—</td>
                            <td>Weight of MB</td>
                            @foreach ($packingRounds as $round)
                                <td class="center">{{ $round->sum_weight_mb ?? '—' }}</td>
                            @endforeach
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>

        <table>
            <tbody>
                <tr>
                    <td style="width: 15%;"><strong>Decision</strong></td>
                    <td style="width: 35%;">
                        {{-- Passed / Hold / Reject with the chosen one circled, like the paper form. --}}
                        @foreach (\App\Models\PackingCheck::DECISIONS as $option)
                            @if ($option === $packingCheck->decision)
                                @include('pdf._status-pill', ['value' => $option])
                            @else
                                <span class="muted">{{ $option }}</span>
                            @endif
                            @if (! $loop->last) / @endif
                        @endforeach
                    </td>
                    <td style="width: 12%;"><strong>Notes</strong></td>
                    <td>{{ $packingCheck->remarks ?? '—' }}</td>
                </tr>
            </tbody>
        </table>

        <div class="attachment-grid" style="grid-template-columns: repeat(2, 1fr);">
            @foreach ([
                ['palletisasi', 'Palletisasi'],
                ['color', 'Color Test'],
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
                            {{ $rev->masterLine ? 'Line: '.$rev->masterLine->name.' ('.$rev->masterLine->code.'). ' : '' }}
                            {{ $rev->decision ? 'Decision: '.$rev->decision.'. ' : '' }}
                            {{ $rev->sum_weight_mb !== null ? 'Weight of MB: '.$rev->sum_weight_mb.'. ' : '' }}
                            {{ $rev->remarks ? 'Remarks: '.$rev->remarks : '' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="muted" style="margin-top: 6px;">CF = Conform &nbsp; NC = Not Conform &nbsp; N/A = Not Applicable &nbsp;&nbsp;|&nbsp;&nbsp; ZD = Zero Defect &nbsp; C = Critical Defect &nbsp; M = Major Defect &nbsp; m = Minor Defect</p>

    @php
        // Issued By = whoever saved the latest TH_PROGRESS round across Filling and Packing.
        $lastRound = collect([$fillingCheck?->revisions, $packingCheck?->revisions])
            ->filter()
            ->flatten(1)
            ->sortByDesc('created_at')
            ->first();
        $issuedBy = $lastRound?->user ?? $packingCheck?->user ?? $fillingCheck?->user;
        $issuedAt = $lastRound?->created_at ?? $packingCheck?->completed_at ?? $fillingCheck?->completed_at;
    @endphp
    <div class="sign-grid">
        <div>
            <span>Issued By (QC Filling / Packing)</span>
            @if ($issuedBy)
                <strong>{{ $issuedBy->name }}</strong>
                <small class="sign-date">{{ optional($issuedAt)->translatedFormat('d/m/Y H:i') ?: '—' }}</small>
            @endif
        </div>
        <div>
            <span>Review By (QC IPC Coordinator)</span>
            @if ($fillingPackingApproval)
                <strong>{{ $fillingPackingApproval->approver->name ?? '—' }}</strong>
                <small class="sign-date">{{ optional($fillingPackingApproval->approved_at)->translatedFormat('d/m/Y H:i') ?: '—' }}</small>
            @endif
        </div>
        <div class="qr-box">
            <span>Verifikasi</span>
            @if ($fillingPackingApproval)
                <div class="qr-code">{!! $verificationQr !!}</div>
                <small>{{ $fillingPackingApproval->decision }}</small>
            @else
                <small class="muted">Belum disetujui</small>
            @endif
        </div>
    </div>
    @include('pdf._approval-remarks', ['approval' => $fillingPackingApproval])
@endsection
