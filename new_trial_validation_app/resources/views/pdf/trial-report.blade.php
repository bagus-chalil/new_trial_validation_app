@extends('pdf.layout')

@php
    $managerDecision = $trial->final_decision ?? $trial->progress_status;
    $hasDecision = filled($trial->approval_comment) || filled($approvedByName) || filled($rejectedByName);
    $decisionBy = $managerDecision === 'Approved' ? $approvedByName : $rejectedByName;
    $decisionAt = $managerDecision === 'Approved' ? $trial->approved_at : $trial->rejected_at;
    $decisionLabels = [
        'Approved' => ['by' => 'Approved By', 'at' => 'Approved At'],
        'Need Revision' => ['by' => 'Revision Requested By', 'at' => 'Revision Requested At'],
        'Rejected' => ['by' => 'Rejected By', 'at' => 'Rejected At'],
    ];
    $decisionLabel = $decisionLabels[$managerDecision] ?? ['by' => 'Decision By', 'at' => 'Decision At'];
    $displayDecision = in_array($trial->progress_status, ['Approved', 'Rejected'], true)
        ? ($trial->final_decision ?? $trial->progress_status)
        : $trial->progress_status;
    $approvalAuthority = $approvedByName ?? $rejectedByName ?? '-';

    $infoFields = [
        'Trial ID' => $trial->trial_code,
        'Product Name' => $trial->product_name,
        'FG Code' => $trial->finish_good_code,
        'Validation Category' => $trial->validation_category,
        'Validation Scope' => implode(', ', $trial->validation_scope ?? []),
        'Product Type' => $trial->product_type,
        'Validation Date' => $trial->validation_date ?? '-',
        'Risk Level' => $trial->risk_level,
        'Machine Used' => implode(', ', $trial->machine_used ?? []),
        'Created By' => $trial->created_by ?? '-',
        'Approval Status' => $displayDecision,
        'Approval Authority' => $approvalAuthority,
    ];
@endphp

@section('content')
    <div class="info-grid">
        @foreach ($infoFields as $label => $value)
            <div>
                <span>{{ $label }}</span>
                <strong>{{ $value ?: '-' }}</strong>
            </div>
        @endforeach
    </div>

    <div class="section-title">Header Detail</div>
    <table>
        <tbody>
            <tr>
                <td><strong>Batch Number</strong></td>
                <td>{{ $trial->batch_number ?? '-' }}</td>
                <td><strong>Bulk Code</strong></td>
                <td>{{ $trial->bulk_code ?? '-' }}</td>
            </tr>
            <tr>
                <td><strong>Support Team</strong></td>
                <td>{{ $trial->support_team ?? '-' }}</td>
                <td><strong>Initiated person/team</strong></td>
                <td>{{ $trial->initiated_person_team ?? '-' }}</td>
            </tr>
            <tr>
                <td><strong>Reason</strong></td>
                <td colspan="3">{{ $trial->reason ?? '-' }}</td>
            </tr>
            <tr>
                <td><strong>B.O.M</strong></td>
                <td colspan="3" style="white-space: pre-line;">{{ $trial->bom ?? '-' }}</td>
            </tr>
            <tr>
                <td><strong>Status</strong></td>
                <td>{{ $trial->progress_status }}</td>
                <td><strong>Pending With</strong></td>
                <td>{{ $trial->pending_with ?? '-' }}</td>
            </tr>
            @if ($trial->approver)
                <tr>
                    <td><strong>Selected Approver</strong></td>
                    <td colspan="3">{{ $trial->approver->name ?: $trial->approver->email }}</td>
                </tr>
            @endif
            <tr>
                <td><strong>Revision No</strong></td>
                <td>{{ $trial->revision_no ?? 0 }}</td>
                <td><strong>Final Decision</strong></td>
                <td>{{ $trial->final_decision ?? '-' }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">Validation Parameter</div>
    <table>
        <thead>
            <tr>
                <th>Parameter</th>
                <th>Spec</th>
                <th>Decision</th>
                <th>Result</th>
                <th>Remark</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($results as $r)
                <tr @class(['notok' => ($r['decision'] ?? null) === 'NOT OK'])>
                    <td>{{ $r['parameter_name'] }}</td>
                    <td style="white-space: pre-line;">{{ $r['specification'] ?? '-' }}</td>
                    <td>{{ $r['decision'] ?? '-' }}</td>
                    <td>{{ $r['result_value'] ?? '-' }}</td>
                    <td>{{ $r['remark'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="muted">Belum ada data validation.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Weighing</div>
    @foreach ($weighingSections as $section)
        <div style="margin-bottom: 8px;">
            <strong>{{ $section['section'] }} Weighing</strong>
            @if (($section['stats']['count'] ?? 0) === 0)
                <p class="muted">{{ $section['section'] }} Weighing: N/A</p>
            @else
                <div class="stats-row">
                    @foreach ($section['stats']['values'] as $v)
                        <span>{{ $v }}</span>
                    @endforeach
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Total Sample</th>
                            <th>Average</th>
                            <th>Minimum</th>
                            <th>Maximum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ $section['stats']['count'] }}</td>
                            <td>{{ $section['stats']['avg'] !== null ? number_format($section['stats']['avg'], 2) : '-' }}</td>
                            <td>{{ $section['stats']['min'] !== null ? number_format($section['stats']['min'], 2) : '-' }}</td>
                            <td>{{ $section['stats']['max'] !== null ? number_format($section['stats']['max'], 2) : '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach

    <div class="section-title">Attachment Summary</div>
    @if (count($attachments) === 0)
        <p class="muted">Tidak ada attachment.</p>
    @else
        @foreach ($attachments as $category => $files)
            <div class="attachment-category">
                <h4>{{ $category }}</h4>
                <div class="attachment-grid">
                    @foreach ($files as $file)
                        <figure class="attachment-tile">
                            @if ($file['src'])
                                <img src="{{ $file['src'] }}" alt="{{ $file['file_name'] }}">
                            @else
                                <p class="muted" style="height: 55mm; display: flex; align-items: center; justify-content: center;">File tidak ditemukan</p>
                            @endif
                            <figcaption>{{ $file['file_name'] }}</figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        @endforeach
    @endif

    <div class="section-title">Department Review</div>
    <table>
        <thead>
            <tr>
                <th>Round</th>
                <th>Dept</th>
                <th>Status</th>
                <th>Reviewer Name</th>
                <th>Reviewed At</th>
                <th>Comment</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($reviews as $r)
                <tr>
                    <td>{{ $r['review_round'] }}</td>
                    <td>{{ $r['department'] }}</td>
                    <td>{{ $r['status'] }}</td>
                    <td>{{ $r['reviewer_name'] ?? '-' }}</td>
                    <td>{{ $r['reviewed_at'] ?? '-' }}</td>
                    <td>{{ $r['comment'] ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($hasDecision)
        <div class="section-title">Manager QAC Decision</div>
        <table>
            <tbody>
                <tr>
                    <td><strong>Decision</strong></td>
                    <td>{{ $managerDecision }}</td>
                    <td><strong>Status</strong></td>
                    <td>{{ $trial->progress_status }}</td>
                </tr>
                <tr>
                    <td><strong>{{ $decisionLabel['by'] }}</strong></td>
                    <td>{{ $decisionBy ?? '-' }}</td>
                    <td><strong>{{ $decisionLabel['at'] }}</strong></td>
                    <td>{{ $decisionAt ?? '-' }}</td>
                </tr>
                <tr>
                    <td><strong>Comment</strong></td>
                    <td colspan="3">{{ $trial->approval_comment ?? '-' }}</td>
                </tr>
            </tbody>
        </table>
    @endif
@endsection
