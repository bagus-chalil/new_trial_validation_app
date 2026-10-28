@extends('pdf.layout')

@section('content')
    {{-- Filling and Packing are separate documents (split 2026-10-20): $side picks which one.
         Both real paper forms (FR.QAC.192.02 / FR.QAC.182.03) open with the same "FORM
         PEMERIKSAAN START UP" section before their own stage-specific content — shared via
         pdf._startup-checklist so the three reports (this one + approval-startup.blade.php) can't
         drift apart on it.

         .fp-compact: both real paper forms are one-page forms (see docs/report/), so this report
         gets extra spacing/font tightening beyond Startup/Finished's own reports — see
         layout.blade.php's .fp-compact rules. Best effort, not a guarantee: a batch with many
         TH_PROGRESS save rounds (a feature with no equivalent on the blank paper form) can still
         spill onto a second page. --}}
    <div class="fp-compact">
    <div class="section-title">Form Pemeriksaan Start Up</div>
    @include('pdf._startup-checklist')

    @if ($side === 'filling' || $side === 'both')
    <div class="section-title" style="margin-top: 6px;">Verifikasi Sebelum Produksi</div>
    @include('pdf._verifikasi-sebelum-produksi')

    <div class="section-title" style="margin-top: 6px;">Filling Inspection</div>

    @if ($fillingCheck)
        <div class="info-grid" style="grid-template-columns: repeat(4, 1fr);">
            <div><span>Date / Shift Filling</span><strong>{{ optional($fillingCheck->completed_at ?? $fillingCheck->updated_at)->translatedFormat('d/m/Y H:i') ?? '—' }}</strong></div>
            <div><span>Machines / Lines</span><strong>{{ $batch->masterLine->name ?? '—' }} ({{ $batch->masterLine->code ?? '—' }})</strong></div>
            {{-- "Standard" on the paper form is one combined min–max value (e.g. "3,92 - 4,00"),
                 confirmed against a real filled example in docs/report/Start up+Filling report.pdf
                 — not two separate boxes as this used to render. --}}
            <div><span>Standard</span><strong>{{ $startupCheck->filling_range_min ?? '—' }} – {{ $startupCheck->filling_range_max ?? '—' }}</strong></div>
            <div><span>QC</span><strong>{{ $fillingCheck->user->name ?? '—' }}</strong></div>
            <div><span>Density</span><strong>{{ $startupCheck?->density_not_applicable ? 'N/A' : ($startupCheck?->density ?? '—') }}</strong></div>
            <div><span>Line Leader</span><strong>{{ $startupCheck->line_leader_name ?? '—' }}</strong></div>
            <div><span>TH Progress</span><strong>{{ $fillingCheck->save_count ?? 0 }}</strong></div>
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

        <table class="record-table record-table--compact">
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

        <table style="margin-bottom: 6px;">
            <tbody>
                <tr>
                    <td style="width: 15%;"><strong>Summary (semua TH Progress)</strong></td>
                    <td style="width: 45%;">
                        Min: {{ $results->isNotEmpty() ? number_format($results->min(), 2) : '—' }}
                        &nbsp;&nbsp; Max: {{ $results->isNotEmpty() ? number_format($results->max(), 2) : '—' }}
                        &nbsp;&nbsp; Average: {{ $results->isNotEmpty() ? number_format($results->avg(), 2) : '—' }}
                    </td>
                    <td style="width: 15%;"><strong>Decision</strong></td>
                    <td>
                        {{-- Passed / Hold / Reject with the chosen one circled, matching the paper
                             form (and the same treatment Packing's own Decision row already uses
                             below). --}}
                        @foreach (\App\Models\FillingCheck::DECISIONS as $option)
                            @if ($option === $fillingCheck->decision)
                                @include('pdf._status-pill', ['value' => $option])
                            @else
                                <span class="muted">{{ $option }}</span>
                            @endif
                            @if (! $loop->last) / @endif
                        @endforeach
                    </td>
                </tr>
                <tr>
                    <td><strong>Remarks</strong></td>
                    <td colspan="3" style="white-space: pre-line;">{{ $fillingCheck->remarks ?? '—' }}</td>
                </tr>
            </tbody>
        </table>

        <div class="attachment-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 4px;">
            @foreach (\App\Models\FillingCheck::PHOTO_LABELS as $field => $label)
                <figure class="attachment-tile attachment-tile--sm">
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
        <p class="muted">{{ $batch->flow_type === 'packing_fg' ? 'Filling Check tidak digunakan pada alur ini.' : 'Filling Check belum diisi.' }}</p>
    @endif

    @if ($fillingCheck && $fillingCheck->revisions->count() > 0)
        <div class="section-title" style="margin-top: 4px;">Riwayat Simpan — Filling Check (TH Progress)</div>
        <table class="record-table--compact">
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
    @endif

    @if ($side === 'packing' || $side === 'both')
    <div class="section-title">Packing Inspection</div>

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

        <div class="section-title" style="margin-top: 4px;">Pemeriksaan Format Coding</div>
        {{-- Format Penulisan Coding per tier = free text typed on Startup Check
             (startup_checks.coding_format_*). Operator (Actual Coding) = the per-tier Startup
             Check coding_primary/secondary/tersier photos (or N/A). --}}
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
                <tr><td>Line Leader</td><td colspan="3">{{ $packingCheck->line_leader_name ?? '—' }}</td></tr>
                <tr><td>QC IPC</td><td colspan="3">{{ $packingCheck->user->name ?? '—' }}</td></tr>
            </tbody>
        </table>

        <div class="section-title">Pemeriksaan Berat Produk</div>
        {{-- Standar Filling / Berat Filling and Standar Berat Masterbox / Berat Masterbox,
             30-sample grids per the paper form's own numbering (see _packing-sample-grid). The
             per-sample readings are the real StartupInspectionSample rows captured once at Start
             Inspection (no separate per-sample weight capture exists on packing_checks itself —
             only the aggregate standard_weight_mb/sum_weight_mb pair). --}}
        {{-- Standar Filling / Berat Filling are not shown on the Packing report, direct user
             request 2026-10-23. --}}
        @php $weightSamplesByNo = $startupInspection?->samples->keyBy('sample_no') ?? collect(); @endphp

        <table>
            <tbody>
                <tr><td style="width: 18%;"><strong>Standar Berat Masterbox</strong></td><td>{{ $packingCheck->standard_weight_mb ?? '—' }}</td></tr>
            </tbody>
        </table>
        @include('pdf._packing-sample-grid', ['label' => 'Berat Masterbox', 'samplesByNo' => $weightSamplesByNo, 'field' => 'weight_master_box'])

        <table>
            <tbody>
                {{-- No single aggregate Appearance / Uji Kebocoran / Uji Fungsional field exists on
                     packing_checks — only the per-tier equivalents in the detailed checklist below
                     (e.g. primary_functional_test_status). Left as a plain gap note rather than
                     guessing which tier's value should stand in for the whole-form summary. --}}
                <tr><td style="width: 18%;"><strong>Appereance</strong></td><td class="muted">— (lihat detail per tier di bawah)</td></tr>
                <tr><td><strong>Uji Kebocoran</strong></td><td class="muted">— (tidak ditangkap terpisah)</td></tr>
                <tr><td><strong>Uji Fungsional</strong></td><td>@include('pdf._status-pill', ['value' => $packingCheck->primary_functional_test_status ?? null])</td></tr>
            </tbody>
        </table>

        {{-- FR.QAC.182.03's own sign-off: two signatures ("Prepared By (Line Leader Production)" /
             "Review By (QC IPC)") at the very bottom of its page — see pdf._startup-checklist's own
             comment for why this is split out from the shared 3-signature block it uses for Filling.
             Same data source as that block's Review By / Verification By fields (line_leader_name /
             the startup check's own verifier), just different roles/position for this form. --}}
        <div class="sign-grid sign-grid--tight sign-grid--role-tight" style="margin: 6px 0;">
            <div>
                <span>Prepared By</span>
                <strong>{{ $startupCheck->line_leader_name ?: '—' }}</strong>
                <small class="role">Line Leader Production</small>
            </div>
            <div>
                <span>Review By</span>
                <strong>{{ $startupCheck->user->name ?? '—' }}</strong>
                <small class="sign-date">{{ optional($startupCheck->completed_at)->translatedFormat('d/m/Y H:i') ?: '—' }}</small>
                <small class="role">QC IPC</small>
            </div>
        </div>

        <div class="section-title">Detail Pemeriksaan Kemasan (Primary / Secondary / Tersier)</div>
        <p class="muted" style="font-size: 8px; margin-top: -4px;">Checklist 17-item ini mengikuti form gabungan FR.QAC.193.00 — tidak ada pada form FR.QAC.182.03 asli, namun datanya tetap ditampilkan di sini karena ini data inspeksi riil yang sudah tercatat di aplikasi.</p>

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
        <table class="record-table record-table--compact">
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
                                        <img src="{{ $roundPhotoUrl }}" alt="{{ $label }}" style="width: 100%; max-height: 11mm; object-fit: contain; margin-top: 2px;">
                                    @elseif ($photoField)
                                        
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
                <figure class="attachment-tile attachment-tile--sm">
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
        <p class="muted">{{ $batch->flow_type === 'filling' ? 'Packing Check tidak digunakan pada alur ini.' : 'Packing Check belum diisi.' }}</p>
    @endif

    @if ($packingCheck && $packingCheck->revisions->count() > 0)
        <div class="section-title" style="margin-top: 2px;">Riwayat Simpan — Packing Check (TH Progress)</div>
        <table class="record-table--compact">
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
    @endif

    {{-- Matches the real paper form's own "Notes" line, present on both FR.QAC.192.02 (Filling)
         and FR.QAC.182.03 (Packing) — previously only rendered on the Packing side by mistake
         (it sat inside the Packing-only @else branch above), leaving CF/NC on the Filling side
         unexplained. --}}
    <p class="muted" style="margin: 2px 0; font-size: 7.5px;">CF = Conform &nbsp; NC = Not Conform &nbsp; N/A = Not Applicable &nbsp;&nbsp;|&nbsp;&nbsp; ZD = Zero Defect &nbsp; C = Critical Defect &nbsp; M = Major Defect &nbsp; m = Minor Defect</p>

    @php
        // One sign-off block per section rendered. On the combined ($side === 'both') document
        // this is two real, separately-timed approvals (Filling can be signed off well before
        // Packing happens), so it renders one block per section rather than collapsing them into
        // the single generic "QC Filling/Packing" box the blank paper form has room for — same
        // "keep the real captured granularity" precedent as the rest of this report.
        $signOffSections = [];
        if ($side === 'filling' || $side === 'both') {
            $signOffSections[] = ['label' => 'Filling', 'check' => $fillingCheck, 'approval' => $side === 'both' ? $fillingApproval : $reportApproval, 'qr' => $side === 'both' ? $fillingVerificationQr : $verificationQr];
        }
        if ($side === 'packing' || $side === 'both') {
            $signOffSections[] = ['label' => 'Packing', 'check' => $packingCheck, 'approval' => $side === 'both' ? $packingApproval : $reportApproval, 'qr' => $side === 'both' ? $packingVerificationQr : $verificationQr];
        }
    @endphp
    @foreach ($signOffSections as $sectionIdx => $section)
        @php
            $lastRound = $section['check']?->revisions?->sortByDesc('created_at')->first();
            $issuedBy = $lastRound?->user ?? $section['check']?->user;
            $issuedAt = $lastRound?->created_at ?? $section['check']?->completed_at;
            $sectionApproval = $section['approval'];
        @endphp
        <div class="sign-grid sign-grid--tight" style="margin: 6px 0;">
            <div>
                <span>Issued By (QC {{ $section['label'] }})</span>
                @if ($issuedBy)
                    <strong>{{ $issuedBy->name }}</strong>
                    <small class="sign-date">{{ optional($issuedAt)->translatedFormat('d/m/Y H:i') ?: '—' }}</small>
                @endif
            </div>
            <div>
                <span>Review By (QC IPC Coordinator)</span>
                @if ($sectionApproval)
                    <strong>{{ $sectionApproval->approver->name ?? '—' }}</strong>
                    <small class="sign-date">{{ optional($sectionApproval->approved_at)->translatedFormat('d/m/Y H:i') ?: '—' }}</small>
                @endif
            </div>
            <div class="qr-box">
                <span>Verifikasi</span>
                @if ($sectionApproval)
                    <div class="qr-code">{!! $section['qr'] !!}</div>
                    <small>{{ $sectionApproval->decision }}</small>
                @else
                    <small class="muted">Belum disetujui</small>
                @endif
            </div>
        </div>
        @include('pdf._approval-remarks', ['approval' => $sectionApproval])
    @endforeach
    </div>
@endsection
