@extends('pdf.layout')

@php
    $fmt = \App\Services\Pdf\ExportFormat::class;

    $managerDecision = $trial->final_decision ?? $trial->progress_status;
    $hasDecision = filled($trial->approval_comment) || filled($approvedByName) || filled($rejectedByName);
    $decisionBy = $managerDecision === 'Approved' ? $approvedByName : $rejectedByName;
    $decisionAt = $managerDecision === 'Approved' ? $trial->approved_at : $trial->rejected_at;
    $decisionLabels = [
        'Approved' => ['by' => __('report.decision.approved_by'), 'at' => __('report.decision.approved_at')],
        'Need Revision' => ['by' => __('report.decision.revision_by'), 'at' => __('report.decision.revision_at')],
        'Rejected' => ['by' => __('report.decision.rejected_by'), 'at' => __('report.decision.rejected_at')],
    ];
    $decisionLabel = $decisionLabels[$managerDecision] ?? ['by' => __('report.decision.decision_by'), 'at' => __('report.decision.decision_at')];
    $displayDecision = in_array($trial->progress_status, ['Approved', 'Rejected'], true)
        ? ($trial->final_decision ?? $trial->progress_status)
        : $trial->progress_status;
    $approvalAuthority = $approvedByName ?? $rejectedByName ?? '-';

    $infoFields = [
        __('report.info.trial_id') => $trial->trial_code,
        __('report.info.product_name') => $trial->product_name,
        __('report.info.fg_code') => $trial->finish_good_code,
        __('report.info.validation_category') => $trial->validation_category,
        __('report.info.validation_scope') => implode(', ', $trial->validation_scope ?? []),
        __('report.info.product_type') => $trial->product_type,
        __('report.info.validation_date') => $fmt::date($trial->validation_date),
        __('report.info.risk_level') => $trial->risk_level,
        __('report.info.machine_used') => implode(', ', $trial->machine_used ?? []),
        __('report.info.created_by') => $trial->created_by ?? '-',
        __('report.info.estimate_qty') => $trial->estimate_qty ?? '-',
        __('report.info.approval_status') => $fmt::trialStatus($displayDecision),
        __('report.info.approval_authority') => $approvalAuthority,
    ];

    // Display labels only — $section['section'] stays the stored value.
    $sectionLabels = [
        'Packaging' => __('report.weighing.sections.packaging'),
        'Filling' => __('report.weighing.sections.filling'),
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

    <div class="section-title">{{ __('report.header.title') }}</div>
    <table>
        <tbody>
            <tr>
                <td><strong>{{ __('report.header.batch_number') }}</strong></td>
                <td>{{ $trial->batch_number ?? '-' }}</td>
                <td><strong>{{ __('report.header.bulk_code') }}</strong></td>
                <td>{{ $trial->bulk_code ?? '-' }}</td>
            </tr>
            <tr>
                <td><strong>{{ __('report.header.estimate_qty') }}</strong></td>
                <td colspan="3">{{ $trial->estimate_qty ?? '-' }}</td>
            </tr>
            <tr>
                <td><strong>{{ __('report.header.support_team') }}</strong></td>
                <td>{{ $trial->support_team ?? '-' }}</td>
                <td><strong>{{ __('report.header.initiated_person_team') }}</strong></td>
                <td>{{ $trial->initiated_person_team ?? '-' }}</td>
            </tr>
            <tr>
                <td><strong>{{ __('report.header.reason') }}</strong></td>
                <td colspan="3">{{ $trial->reason ?? '-' }}</td>
            </tr>
            <tr>
                <td><strong>{{ __('report.header.bom') }}</strong></td>
                <td colspan="3" style="white-space: pre-line;">{{ $trial->bom ?? '-' }}</td>
            </tr>
            <tr>
                <td><strong>{{ __('report.header.status') }}</strong></td>
                <td>{{ $fmt::trialStatus($trial->progress_status) }}</td>
                <td><strong>{{ __('report.header.pending_with') }}</strong></td>
                <td>{{ $trial->pending_with ?? '-' }}</td>
            </tr>
            @if ($trial->approver)
                <tr>
                    <td><strong>{{ __('report.header.selected_approver') }}</strong></td>
                    <td colspan="3">{{ $trial->approver->name ?: $trial->approver->email }}</td>
                </tr>
            @endif
            <tr>
                <td><strong>{{ __('report.header.revision_no') }}</strong></td>
                <td>{{ $trial->revision_no ?? 0 }}</td>
                <td><strong>{{ __('report.header.final_decision') }}</strong></td>
                <td>{{ $trial->final_decision ? $fmt::trialStatus($trial->final_decision) : '-' }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">{{ __('report.validation.title') }}</div>
    <table>
        <thead>
            <tr>
                <th>{{ __('report.validation.parameter') }}</th>
                <th>{{ __('report.validation.specification') }}</th>
                <th>{{ __('report.validation.decision') }}</th>
                <th>{{ __('report.validation.result') }}</th>
                <th>{{ __('report.validation.remark') }}</th>
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
                    <td colspan="5" class="muted">{{ __('report.validation.empty') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">{{ __('report.weighing.title') }}</div>
    @foreach ($weighingSections as $section)
        @php($sectionLabel = $sectionLabels[$section['section']] ?? $section['section'])
        <div style="margin-bottom: 8px;">
            <strong>{{ __('report.weighing.section_title', ['section' => $sectionLabel]) }}</strong>
            @if (($section['stats']['count'] ?? 0) === 0)
                <p class="muted">{{ __('report.weighing.not_available', ['section' => $sectionLabel]) }}</p>
            @else
                <div class="stats-row">
                    @foreach ($section['stats']['values'] as $v)
                        <span>{{ $v }}</span>
                    @endforeach
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('report.weighing.total_sample') }}</th>
                            <th>{{ __('report.weighing.average') }}</th>
                            <th>{{ __('report.weighing.minimum') }}</th>
                            <th>{{ __('report.weighing.maximum') }}</th>
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

    @if ($includeAttachments)
        <div class="section-title">{{ __('report.attachments.title') }}</div>
        @if (count($attachments) === 0)
            <p class="muted">{{ __('report.attachments.empty') }}</p>
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
                                    <p class="muted" style="height: 55mm; display: flex; align-items: center; justify-content: center;">{{ __('exports.attachments.file_not_found') }}</p>
                                @endif
                                <figcaption>
                                    @if (filled($file['caption'] ?? null))
                                        <strong>{{ $file['caption'] }}</strong><br>
                                    @endif
                                    {{ $file['file_name'] }}
                                </figcaption>
                            </figure>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif

        <div class="section-title">{{ __('report.additional.title') }}</div>
        @if (count($additionalAttachments) === 0)
            <p class="muted">{{ __('report.additional.empty') }}</p>
        @else
            <div class="attachment-grid">
                @foreach ($additionalAttachments as $file)
                    <figure class="attachment-tile">
                        @if ($file['src'])
                            <img src="{{ $file['src'] }}" alt="{{ $file['original_name'] }}">
                        @else
                            <p class="muted" style="height: 55mm; display: flex; align-items: center; justify-content: center; text-align: center;">
                                {{ $file['is_pdf'] ? __('exports.attachments.pdf_listed_only') : __('exports.attachments.file_not_found') }}
                            </p>
                        @endif
                        <figcaption>
                            @if (filled($file['description'] ?? null))
                                <strong>{{ $file['description'] }}</strong><br>
                            @endif
                            {{ $file['original_name'] }}<br>
                            <span class="muted">{{ $file['uploaded_by_name'] ?? '-' }} · {{ $fmt::dateTime($file['created_at'] ?? null) }}</span>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        @endif
    @endif

    <div class="section-title">{{ __('report.review.title') }}</div>
    <table>
        <thead>
            <tr>
                <th>{{ __('report.review.round') }}</th>
                <th>{{ __('report.review.department') }}</th>
                <th>{{ __('report.review.status') }}</th>
                <th>{{ __('report.review.reviewer_name') }}</th>
                <th>{{ __('report.review.reviewed_at') }}</th>
                <th>{{ __('report.review.comment') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($reviews as $r)
                <tr>
                    <td>{{ $r['review_round'] }}</td>
                    <td>{{ $r['department'] }}</td>
                    <td>{{ $fmt::reviewStatus($r['status']) }}</td>
                    <td>{{ $r['reviewer_name'] ?? ($r['assigned_to'] ? $r['assigned_to'].' '.__('report.review.assigned') : '-') }}</td>
                    <td>{{ $fmt::dateTime($r['reviewed_at'] ?? null) }}</td>
                    <td>{{ $r['comment'] ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($hasDecision)
        <div class="section-title">{{ __('report.decision.title') }}</div>
        <table>
            <tbody>
                <tr>
                    <td><strong>{{ __('report.decision.decision') }}</strong></td>
                    <td>{{ $fmt::trialStatus($managerDecision) }}</td>
                    <td><strong>{{ __('report.decision.status') }}</strong></td>
                    <td>{{ $fmt::trialStatus($trial->progress_status) }}</td>
                </tr>
                <tr>
                    <td><strong>{{ $decisionLabel['by'] }}</strong></td>
                    <td>{{ $decisionBy ?? '-' }}</td>
                    <td><strong>{{ $decisionLabel['at'] }}</strong></td>
                    <td>{{ $fmt::dateTime($decisionAt) }}</td>
                </tr>
                <tr>
                    <td><strong>{{ __('report.decision.comment') }}</strong></td>
                    <td colspan="3">{{ $trial->approval_comment ?? '-' }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    @if ($includeLineConfiguration)
        <div class="section-title" style="page-break-before: always;">{{ __('line_config.title') }}</div>
        @if ($lineConfigurationReport)
            @include('pdf.partials.line-configuration-report', ['report' => $lineConfigurationReport])
        @else
            <p class="muted">{{ __('line_config.empty') }}</p>
        @endif
    @endif
@endsection
