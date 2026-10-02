{{-- Shared body of a Line Configuration Report PDF — used by the locked
     per-version download and the optional section of the trial report PDF. --}}
@php
    $fmt = \App\Services\Pdf\ExportFormat::class;

    // approved_pie_label/checked_prod_label are this locked version's own
    // frozen snapshot (Phase 3 of the RBAC/Team-master redesign — see
    // LineConfigurationLane) — a later admin rename of the live lane must
    // never change how an already-locked historical PDF reads, so this
    // never falls back to the live config, only to the hardcoded default
    // for rows saved before this column existed. Lane names are
    // admin-configured data, so they are never translated.
    $signOffFields = [
        ['field' => 'approved_pie', 'label' => $report->approved_pie_label ?: 'Approved (PIE)'],
        ['field' => 'checked_prod', 'label' => $report->checked_prod_label ?: 'Checked (PROD)'],
        ['field' => 'return_prod', 'label' => __('exports.line_config.return_prod')],
    ];
    $capacityLabel = $report->capacity_label ?: __('line_config.columns.capacity');
@endphp


    <p class="badge">{{ __('exports.line_config.version', ['version' => $report->version]) }} — {{ $report->is_locked ? __('exports.line_config.locked') : __('exports.line_config.latest') }}</p>

    <div class="info-grid">
        <div><span>{{ __('line_config.fields.date') }}</span><strong>{{ $report->report_date ? $fmt::date($report->report_date) : '-' }}</strong></div>
        <div><span>{{ __('line_config.fields.client') }}</span><strong>{{ $report->client_name ?: '-' }}</strong></div>
        <div><span>{{ __('line_config.fields.validation') }}</span><strong>{{ $report->validation_name ?: '-' }}</strong></div>
        <div><span>{{ __('line_config.fields.pic') }}</span><strong>{{ $report->pic ?: '-' }}</strong></div>
        <div><span>{{ __('line_config.fields.operator') }}</span><strong>{{ $report->operator ?: '-' }}</strong></div>
        <div><span>{{ $capacityLabel }}</span><strong>-</strong></div>
        <div><span>{{ __('line_config.fields.total') }}</span><strong>{{ $report->total_qty ?: '-' }}</strong></div>
        <div><span>{{ __('line_config.fields.setting') }}</span><strong>{{ $report->setting_qty ?: '-' }}</strong></div>
        <div><span>{{ __('line_config.fields.pass') }}</span><strong>{{ $report->pass_qty ?: '-' }}</strong></div>
        <div><span>{{ __('line_config.fields.ng') }}</span><strong>{{ $report->ng_qty ?: '-' }}</strong></div>
    </div>

    <div class="section-title">{{ __('line_config.sign_off.title') }}</div>
    <table>
        <thead>
            <tr>
                <th>{{ __('line_config.stage.prepared') }}</th>
                @foreach ($signOffFields as $f)
                    <th>{{ $f['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $report->pic ?: '-' }}</td>
                @foreach ($signOffFields as $f)
                    <td>
                        @if ($report->{$f['field']})
                            {{ $report->{$f['field'].'_by'} ?: __('exports.line_config.yes') }}
                            @if ($report->{$f['field'].'_at'})
                                <br><span class="muted">{{ $fmt::date($report->{$f['field'].'_at'}) }}</span>
                            @endif
                            @if (in_array($f['field'], ['approved_pie', 'checked_prod']) && $report->{$f['field'].'_comment'})
                                <br><span class="muted">&ldquo;{{ $report->{$f['field'].'_comment'} }}&rdquo;</span>
                            @endif
                        @else
                            -
                        @endif
                    </td>
                @endforeach
            </tr>
        </tbody>
    </table>

    <div class="section-title">{{ __('line_config.sections.production_standard') }}</div>
    <table>
        <thead>
            <tr>
                <th>{{ __('line_config.columns.line') }}</th>
                <th>{{ __('line_config.columns.workers') }}</th>
                <th>{{ $capacityLabel }}</th>
                <th>{{ __('line_config.columns.remark') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse (($report->production_standard ?? []) as $row)
                <tr>
                    <td>{{ $row['line'] ?? '-' }}</td>
                    <td>{{ $row['workers'] ?? '-' }}</td>
                    <td>{{ $row['capacity'] ?? '-' }}</td>
                    <td>{{ $row['remark'] ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="4">{{ __('line_config.no_data') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">{{ __('line_config.sections.line_configuration') }}</div>
    <table>
        <thead>
            <tr>
                <th>{{ __('line_config.columns.no') }}</th>
                <th>{{ __('line_config.columns.equipment') }}</th>
                <th>{{ __('line_config.columns.process') }}</th>
                <th>{{ __('line_config.columns.worker') }}</th>
                <th>{{ __('line_config.columns.trial') }}</th>
                <th>{{ __('line_config.columns.remark') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse (($report->line_configuration ?? []) as $i => $row)
                <tr>
                    <td>{{ $row['no'] ?? $i + 1 }}</td>
                    <td>{{ $row['equipment'] ?? '-' }}</td>
                    <td>{{ $row['process'] ?? '-' }}</td>
                    <td>{{ $row['worker'] ?? '-' }}</td>
                    <td>{{ $fmt::lineTrialStatus($row['trial_status'] ?? null) }}</td>
                    <td>{{ $row['remark'] ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="6">{{ __('line_config.no_data') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($report->opinion)
        <div class="section-title">{{ __('line_config.fields.opinion') }}</div>
        <p>{{ $report->opinion }}</p>
    @endif
