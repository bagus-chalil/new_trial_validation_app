@extends('pdf.layout')

@push('head')
<style>
    /* FR.QAC.193.00 is one document with two paper pages: page 1 portrait (Filling + Packing),
       page 2 landscape (Finished Good). Needs Browsershot's preferCSSPageSize (PdfService
       $cssPageSize) - the named page below switches orientation mid-document. */
    @page { size: A4 portrait; margin: 10mm; }
    @page fg { size: A4 landscape; margin: 10mm; }
    .fg-page { page: fg; break-before: page; page-break-before: always; }
</style>
@endpush

@section('content')
{{-- Page 1 (portrait): sections A and B of the paper form - Filling Inspection and Packing
     Inspection in one page, one column per TH_PROGRESS round. The paper form has no Startup
     section, photos or save history on this page, so none are rendered here. --}}
<div class="fp-compact fp-form">
    <div class="section-title">A. Filling Inspection</div>
    @if ($fillingCheck)
        <div class="info-grid">
            <div><span>Date / Shift Filling</span><strong>{{ optional($fillingCheck->completed_at ?? $fillingCheck->updated_at)->translatedFormat('d/m/Y H:i') ?? '—' }}</strong></div>
            <div><span>Machines / Lines</span><strong>{{ $batch->masterLine->name ?? '—' }} ({{ $batch->masterLine->code ?? '—' }})</strong></div>
            <div><span>Standard</span><strong>{{ $startupCheck->filling_range_min ?? '—' }} – {{ $startupCheck->filling_range_max ?? '—' }}</strong></div>
            <div><span>QC</span><strong>{{ $fillingCheck->user->name ?? '—' }}</strong></div>
            <div><span>Density</span><strong>{{ $startupCheck?->density_not_applicable ? 'N/A' : ($startupCheck?->density ?? '—') }}</strong></div>
            <div><span>Line Leader</span><strong>{{ $startupCheck->line_leader_name ?? '—' }}</strong></div>
            <div><span>TH Progress</span><strong>{{ $fillingCheck->save_count ?? 0 }}</strong></div>
        </div>

        @php
            $fillingRounds = $fillingCheck->revisions->sortBy('revision_no')->values();
            if ($fillingRounds->isEmpty()) {
                $fillingRounds = collect([$fillingCheck]);
            }
            $results = $fillingRounds->flatMap(fn ($round) => $round->samples)->pluck('weight_value')->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v);
            $fillingTimeColWidth = (100 - 16) / max($fillingRounds->count(), 1);
        @endphp
        <table class="record-table record-table--compact">
            <colgroup>
                <col style="width: 16%;">
                @foreach ($fillingRounds as $round)
                    <col style="width: {{ $fillingTimeColWidth }}%;">
                @endforeach
            </colgroup>
            <thead>
                <tr><th rowspan="2">Parameter</th><th colspan="{{ $fillingRounds->count() }}">Time</th></tr>
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
                    <td>Cleaness Bulk &amp; Odor</td>
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
                    <td style="width: 16%;"><strong>Summary</strong></td>
                    <td style="width: 44%;">
                        Min: {{ $results->isNotEmpty() ? number_format($results->min(), 2) : '—' }}
                        &nbsp; Max: {{ $results->isNotEmpty() ? number_format($results->max(), 2) : '—' }}
                        &nbsp; Average: {{ $results->isNotEmpty() ? number_format($results->avg(), 2) : '—' }}
                    </td>
                    <td style="width: 12%;"><strong>Decision</strong></td>
                    <td>
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
                <tr><td><strong>Remarks</strong></td><td colspan="3" style="white-space: pre-line;">{{ $fillingCheck->remarks ?? '—' }}</td></tr>
            </tbody>
        </table>
    @else
        <p class="muted">Filling Check belum diisi.</p>
    @endif

    <div class="section-title">B. Packing Inspection</div>
    @if ($packingCheck)
        <div class="info-grid">
            <div><span>Date / Shift Packing</span><strong>{{ optional($packingCheck->completed_at ?? $packingCheck->updated_at)->translatedFormat('d/m/Y H:i') ?? '—' }}</strong></div>
            <div><span>Machines / Lines</span><strong>{{ $packingCheck->masterLine->name ?? $batch->masterLine->name ?? '—' }} ({{ $packingCheck->masterLine->code ?? $batch->masterLine->code ?? '—' }})</strong></div>
            <div><span>Machines Coding</span><strong>{{ $packingCheck->coding_machine ?? '—' }}</strong></div>
            <div><span>QC</span><strong>{{ $packingCheck->user->name ?? '—' }}</strong></div>
            <div><span>Line Leader</span><strong>{{ $packingCheck->line_leader_name ?? '—' }}</strong></div>
            <div><span>Std Bruto MB</span><strong>{{ $packingCheck->standard_weight_mb ?? '—' }}</strong></div>
            <div><span>Data Timbang</span><strong>{{ $packingCheck->weighing_data ?? '—' }}</strong></div>
            <div><span>TH Progress</span><strong>{{ $packingCheck->save_count ?? 0 }}</strong></div>
        </div>

        @php
            $packingRounds = $packingCheck->revisions->sortBy('revision_no')->values();
            if ($packingRounds->isEmpty()) {
                $packingRounds = collect([$packingCheck]);
            }
            $roundCount = max($packingRounds->count(), 1);
            $timeColWidth = (100 - 6 - 6 - 24) / $roundCount;
        @endphp
        <table class="record-table record-table--compact">
            <colgroup>
                <col style="width: 6%;"><col style="width: 6%;"><col style="width: 24%;">
                @foreach ($packingRounds as $round)
                    <col style="width: {{ $timeColWidth }}%;">
                @endforeach
            </colgroup>
            <thead>
                <tr><th rowspan="2">No</th><th rowspan="2">Kode</th><th rowspan="2">Parameter</th><th colspan="{{ $packingRounds->count() }}">Time</th></tr>
                <tr>
                    @foreach ($packingRounds as $round)
                        <th class="center">{{ optional($round->created_at)->format('d/m H:i') ?? '—' }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="3"><strong>Machines / Lines</strong></td>
                    @foreach ($packingRounds as $round)
                        @php $roundLine = $round->masterLine ?? $packingCheck->masterLine ?? $batch->masterLine; @endphp
                        <td class="center">{{ $roundLine->code ?? '—' }}</td>
                    @endforeach
                </tr>
                @foreach ($packingChecklistGroups as $group)
                    <tr class="group-row"><td colspan="{{ 3 + $packingRounds->count() }}">{{ ucfirst($group['key']) }} Packaging</td></tr>
                    @foreach (array_keys($group['fields']) as $index => $field)
                        <tr>
                            <td class="center">{{ $index + 1 }}</td>
                            <td class="center">{{ \App\Models\PackingCheck::SEVERITY_LABELS[$field] ?? '—' }}</td>
                            <td>{{ $group['fields'][$field] }}</td>
                            @foreach ($packingRounds as $round)
                                <td class="center">@include('pdf._status-pill', ['value' => $round[$field] ?? null, 'abbreviate' => true])</td>
                            @endforeach
                        </tr>
                    @endforeach
                    @if ($group['key'] === 'tersier')
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
                    <td style="width: 12%;"><strong>Decision</strong></td>
                    <td style="width: 38%;">
                        @foreach (\App\Models\PackingCheck::DECISIONS as $option)
                            @if ($option === $packingCheck->decision)
                                @include('pdf._status-pill', ['value' => $option])
                            @else
                                <span class="muted">{{ $option }}</span>
                            @endif
                            @if (! $loop->last) / @endif
                        @endforeach
                    </td>
                    <td style="width: 10%;"><strong>Notes</strong></td>
                    <td>{{ $packingCheck->remarks ?? '—' }}</td>
                </tr>
            </tbody>
        </table>
    @else
        <p class="muted">Packing Check belum diisi.</p>
    @endif

    <p class="muted" style="margin: 2px 0;">CF = Conform &nbsp; NC = Not Conform &nbsp; N/A = Not Applicable &nbsp;|&nbsp; ZD = Zero Defect &nbsp; C = Critical Defect &nbsp; M = Major Defect &nbsp; m = Minor Defect</p>

    @php
        $signOffSections = [
            ['label' => 'Filling', 'check' => $fillingCheck, 'approval' => $fillingApproval, 'qr' => $fillingVerificationQr],
            ['label' => 'Packing', 'check' => $packingCheck, 'approval' => $packingApproval, 'qr' => $packingVerificationQr],
        ];
    @endphp
    @foreach ($signOffSections as $section)
        @php
            $lastRound = $section['check']?->revisions?->sortByDesc('created_at')->first();
            $issuedBy = $lastRound?->user ?? $section['check']?->user;
            $issuedAt = $lastRound?->created_at ?? $section['check']?->completed_at;
            $sectionApproval = $section['approval'];
        @endphp
        <div class="sign-grid sign-grid--tight">
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
    @endforeach
</div>

{{-- Page 2 (landscape): Finished Good, the second paper page of FR.QAC.193.00, with its own header. --}}
<div class="fg-page">
    @php
        $fgLogoPath = resource_path('images/cosmax-idn-logo.jpg');
        $fgLogo = is_file($fgLogoPath) ? 'data:image/jpeg;base64,'.base64_encode(file_get_contents($fgLogoPath)) : null;
    @endphp
    <div class="report-header">
        <div class="brand">@if ($fgLogo)<img src="{{ $fgLogo }}" alt="Cosmax">@else IPC @endif</div>
        <div><div class="title">Laporan Pemeriksaan Finished Good</div></div>
        <div class="meta">
            <div class="form-number">No. Doc: {{ $docNumber ?? 'FR.QAC.193.00' }}</div>
            <div>Rev: {{ $docRevision ?? '0' }}</div>
            <div>No. Batch: {{ $batch->no_batch }}</div>
        </div>
    </div>
    @include('pdf._finished-body', ['verificationQr' => $finishedVerificationQr])
</div>
@endsection
