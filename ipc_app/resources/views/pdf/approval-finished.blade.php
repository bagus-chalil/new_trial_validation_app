@extends('pdf.layout')

@section('content')
    <div class="section-title">Finished Good Inspection Report</div>

    @if ($finishedCheck)
        <div class="attachment-grid">
            @foreach ([['wi_number', 'WI Number'], ['exp_date', 'Exp Date'], ['color', 'Color Test']] as [$field, $label])
                <figure class="attachment-tile">
                    @if ($photoUrls['finished'][$field] ?? null)
                        <img src="{{ $photoUrls['finished'][$field] }}" alt="{{ $label }}">
                    @else
                        <div class="placeholder">Belum ada foto</div>
                    @endif
                    <figcaption><strong>{{ $label }}</strong></figcaption>
                </figure>
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
                    <td><strong>Qty Sampling AQL</strong></td>
                    <td colspan="3">
                        {{ $finishedCheck->quantity_sampling_aql ?? '—' }}
                        (CD {{ $finishedCheck->quantity_sample_aql_cd ?? '—' }} /
                        MD {{ $finishedCheck->quantity_sample_aql_md ?? '—' }} /
                        mD {{ $finishedCheck->quantity_sample_aql_mnd ?? '—' }})
                    </td>
                </tr>
                <tr>
                    <td><strong>Qty Special Inspection</strong></td>
                    <td colspan="3">
                        {{ $finishedCheck->quantity_special_inspection ?? '—' }}
                        (CD {{ $finishedCheck->quantity_special_inspection_cd ?? '—' }} /
                        MD {{ $finishedCheck->quantity_special_inspection_md ?? '—' }} /
                        mD {{ $finishedCheck->quantity_special_inspection_mnd ?? '—' }})
                    </td>
                </tr>
            </tbody>
        </table>

        @php $samplesByKey = $finishedCheck->samples->keyBy('parameter_key'); @endphp
        <table>
            <thead>
                <tr>
                    <th>Parameter</th>
                    <th class="center" style="width: 10%;">AC</th>
                    <th class="center" style="width: 10%;">CD</th>
                    <th class="center" style="width: 10%;">MD</th>
                    <th class="center" style="width: 10%;">mD</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($finishedSampleGroups as $group)
                    <tr><td colspan="5" style="background:#f9fafb;"><strong>{{ $group['label'] }}</strong></td></tr>
                    @foreach ($group['parameters'] as $key => $label)
                        @php $row = $samplesByKey[$key] ?? null; @endphp
                        <tr>
                            <td style="padding-left: 14px;">{{ $label }}</td>
                            <td class="center">{{ $row->ac ?? '—' }}</td>
                            <td class="center">{{ $row->cd ?? '—' }}</td>
                            <td class="center">{{ $row->md ?? '—' }}</td>
                            <td class="center">{{ $row->mnd ?? '—' }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
        <p class="muted">AC = Accepted Sample, CD = Critical Defect, MD = Major Defect, mD = Minor Defect.</p>

        <table>
            <tbody>
                <tr>
                    <td style="width: 15%;"><strong>Disposition</strong></td>
                    <td style="width: 25%;">@include('pdf._status-pill', ['value' => $finishedCheck->disposition])</td>
                    <td style="width: 15%;"><strong>QC FG Inspector</strong></td>
                    <td>{{ $finishedCheck->user->name ?? '—' }}</td>
                </tr>
                <tr>
                    <td><strong>Remarks</strong></td>
                    <td colspan="3">{{ $finishedCheck->remarks ?? '—' }}</td>
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
                        @php $filled = $rev->samples->filter(fn ($s) => $s->ac || $s->cd || $s->md || $s->mnd)->count(); @endphp
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
            <div><span>QC FG Inspector</span></div>
            <div><span>QC Staff</span></div>
        </div>
    @else
        <p class="muted">Finished Check belum diisi.</p>
    @endif
@endsection
