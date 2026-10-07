@extends('pdf.layout')

@section('content')
    <div class="section-title">Finished Good Inspection Report</div>

    @if ($finishedCheck)
        <div class="attachment-grid">
            {{-- wi_number holds up to 4 photos; one tile per photo. --}}
            @foreach ([['wi_number', 'WI Number'], ['exp_date', 'Exp Date'], ['color', 'Color Test']] as [$field, $label])
                @php
                    $urls = $photoUrls['finished'][$field] ?? null;
                    $urls = is_array($urls) ? array_values(array_filter($urls)) : array_filter([$urls]);
                @endphp
                @forelse ($urls as $i => $url)
                    <figure class="attachment-tile">
                        <img src="{{ $url }}" alt="{{ $label }}">
                        <figcaption><strong>{{ $label }}{{ count($urls) > 1 ? ' '.($i + 1) : '' }}</strong></figcaption>
                    </figure>
                @empty
                    <figure class="attachment-tile">
                        <div class="placeholder">Belum ada foto</div>
                        <figcaption><strong>{{ $label }}</strong></figcaption>
                    </figure>
                @endforelse
            @endforeach
        </div>

        <table>
            <tbody>
                <tr>
                    <td style="width: 20%;"><strong>Quantity WI</strong></td><td style="width: 30%;">{{ $finishedCheck->quantity_wi ?? '—' }}</td>
                    <td style="width: 20%;"><strong>Masterbox</strong></td><td>{{ $finishedCheck->masterbox ?? '—' }}</td>
                </tr>
                <tr>
                    <td><strong>No. Pallet & Qty</strong></td><td>{{ $finishedCheck->no_pallet_qty ?? '—' }}</td>
                    <td><strong>Line Leader</strong></td><td>{{ $finishedCheck->line_leader_name ?? '—' }}</td>
                </tr>
                <tr>
                    <td><strong>TH Progress</strong></td><td>{{ $finishedCheck->save_count ?? 0 }}</td>
                    <td><strong>QC FG Inspector</strong></td><td>{{ $finishedCheck->user->name ?? '—' }}</td>
                </tr>
            </tbody>
        </table>

        <div class="two-col">
            <table>
                <thead><tr><th colspan="2">General Inspection</th></tr></thead>
                <tbody>
                    <tr><td style="width: 45%;">Qty of Sampling</td><td>{{ $finishedCheck->quantity_sampling_aql ?? '—' }}</td></tr>
                    <tr><td>CD / MD / mD</td><td>{{ $finishedCheck->quantity_sample_aql_cd ?? '—' }} / {{ $finishedCheck->quantity_sample_aql_md ?? '—' }} / {{ $finishedCheck->quantity_sample_aql_mnd ?? '—' }}</td></tr>
                </tbody>
            </table>
            <table>
                <thead><tr><th colspan="2">Special Inspection</th></tr></thead>
                <tbody>
                    <tr><td style="width: 45%;">Qty of Sampling</td><td>{{ $finishedCheck->quantity_special_inspection ?? '—' }}</td></tr>
                    <tr><td>CD / MD / mD</td><td>{{ $finishedCheck->quantity_special_inspection_cd ?? '—' }} / {{ $finishedCheck->quantity_special_inspection_md ?? '—' }} / {{ $finishedCheck->quantity_special_inspection_mnd ?? '—' }}</td></tr>
                </tbody>
            </table>
        </div>

        @php $samplesByKey = $finishedCheck->samples->keyBy('parameter_key'); $itemNo = 0; @endphp
        <table>
            <thead>
                <tr>
                    <th style="width: 6%;">No</th>
                    <th>Parameter</th>
                    <th class="center" style="width: 10%;">AC</th>
                    <th class="center" style="width: 10%;">CD</th>
                    <th class="center" style="width: 10%;">MD</th>
                    <th class="center" style="width: 10%; text-transform: none;">mD</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($finishedSampleGroups as $group)
                    <tr><td colspan="6" style="background:#f9fafb;"><strong>{{ $group['label'] }} Packaging</strong></td></tr>
                    @foreach ($group['parameters'] as $key => $label)
                        @php $itemNo++; $row = $samplesByKey[$key] ?? null; @endphp
                        <tr>
                            <td class="center">{{ $itemNo }}</td>
                            <td style="padding-left: 14px;">{{ $label }}</td>
                            <td class="center">{{ $row->ac ?? 'N/A' }}</td>
                            <td class="center">{{ $row->cd ?? 'N/A' }}</td>
                            <td class="center">{{ $row->md ?? 'N/A' }}</td>
                            <td class="center">{{ $row->mnd ?? 'N/A' }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
        <p class="muted">ZD = Zero Defect &nbsp; C = Critical Defect &nbsp; M = Major Defect &nbsp; m = Minor Defect &nbsp; (AC = Accepted Sample count per parameter)</p>

        <table>
            <tbody>
                <tr>
                    <td style="width: 15%;"><strong>Disposition</strong></td>
                    <td style="width: 25%;">@include('pdf._status-pill', ['value' => $finishedCheck->disposition])</td>
                    <td style="width: 15%;"><strong>Color Test</strong></td>
                    <td>Lihat foto Color di atas</td>
                </tr>
                <tr>
                    <td><strong>Remarks</strong></td>
                    <td colspan="3" style="white-space: pre-line;">{{ $finishedCheck->remarks ?? '—' }}</td>
                </tr>
            </tbody>
        </table>

        @if ($finishedCheck->revisions->count() > 0)
            <div class="section-title" style="margin-top: 4px;">Riwayat Simpan — Finished Check (TH Progress)</div>
            <table>
                <thead>
                    <tr><th style="width: 6%;">#</th><th style="width: 20%;">Waktu</th><th style="width: 16%;">User</th><th style="width: 10%;">Status</th><th>Ringkasan</th></tr>
                </thead>
                <tbody>
                    @foreach ($finishedCheck->revisions->sortByDesc('revision_no') as $rev)
                        @php $filled = $rev->samples->filter(fn ($s) => $s->ac !== null || $s->cd !== null || $s->md !== null || $s->mnd !== null)->count(); @endphp
                        <tr>
                            <td class="center">{{ $rev->revision_no }}</td>
                            <td>{{ optional($rev->created_at)->translatedFormat('d M Y H:i') ?? '—' }}</td>
                            <td>{{ $rev->user->name ?? '—' }}</td>
                            <td>{{ $rev->finalize ? 'Selesai' : 'Draft' }}</td>
                            <td>
                                {{ $rev->disposition ? 'Disposition: '.$rev->disposition.'. ' : '' }}
                                Sample: {{ $filled }}/{{ count(\App\Models\FinishedCheckSample::PARAMETER_KEYS) }}.
                                {{ $rev->remarks ? ' Remarks: '.$rev->remarks : '' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="sign-grid">
            <div>
                <span>QC FG Inspector</span>
                <strong>{{ $finishedCheck->user->name ?? '—' }}</strong>
                <small class="sign-date">{{ optional($finishedCheck->completed_at)->translatedFormat('d/m/Y H:i') ?: '—' }}</small>
            </div>
            <div>
                <span>QC Approval</span>
                @if ($finishedApproval)
                    <strong>{{ $finishedApproval->approver->name ?? '—' }}</strong>
                    <small class="sign-date">{{ optional($finishedApproval->approved_at)->translatedFormat('d/m/Y H:i') ?: '—' }}</small>
                @endif
            </div>
            <div class="qr-box">
                <span>Verifikasi</span>
                @if ($finishedApproval)
                    <div class="qr-code">{!! $verificationQr !!}</div>
                    <small>{{ $finishedApproval->decision }}</small>
                @else
                    <small class="muted">Belum disetujui</small>
                @endif
            </div>
        </div>
    @else
        <p class="muted">Finished Check belum diisi.</p>
    @endif
    @include('pdf._approval-remarks', ['approval' => $finishedApproval])
@endsection
