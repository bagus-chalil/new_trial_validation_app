@extends('pdf.layout')

@php
    // approved_pie_label/checked_prod_label are this locked version's own
    // frozen snapshot (Phase 3 of the RBAC/Team-master redesign — see
    // LineConfigurationLane) — a later admin rename of the live lane must
    // never change how an already-locked historical PDF reads, so this
    // never falls back to the live config, only to the hardcoded default
    // for rows saved before this column existed.
    $signOffFields = [
        ['field' => 'approved_pie', 'label' => $report->approved_pie_label ?: 'Approved (PIE)'],
        ['field' => 'checked_prod', 'label' => $report->checked_prod_label ?: 'Checked (PROD)'],
        ['field' => 'return_prod', 'label' => 'Return (PROD)'],
    ];
@endphp

@section('content')
    <p class="badge">Version {{ $report->version }} — Locked (read-only)</p>

    <div class="info-grid">
        <div><span>Date</span><strong>{{ optional($report->report_date)->format('d M Y') ?? '-' }}</strong></div>
        <div><span>Client</span><strong>{{ $report->client_name ?: '-' }}</strong></div>
        <div><span>Validation</span><strong>{{ $report->validation_name ?: '-' }}</strong></div>
        <div><span>PIC</span><strong>{{ $report->pic ?: '-' }}</strong></div>
        <div><span>Operator</span><strong>{{ $report->operator ?: '-' }}</strong></div>
        <div><span>{{ $report->capacity_label ?: 'Kapasitas/Speed' }}</span><strong>-</strong></div>
        <div><span>Total</span><strong>{{ $report->total_qty ?: '-' }}</strong></div>
        <div><span>Setting</span><strong>{{ $report->setting_qty ?: '-' }}</strong></div>
        <div><span>PASS</span><strong>{{ $report->pass_qty ?: '-' }}</strong></div>
        <div><span>NG</span><strong>{{ $report->ng_qty ?: '-' }}</strong></div>
    </div>

    <div class="section-title">Sign-off</div>
    <table>
        <thead>
            <tr>
                <th>Prepared (PIE)</th>
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
                            {{ $report->{$f['field'].'_by'} ?: 'Ya' }}
                            @if ($report->{$f['field'].'_at'})
                                <br><span class="muted">{{ $report->{$f['field'].'_at'}->format('d M Y') }}</span>
                            @endif
                        @else
                            -
                        @endif
                    </td>
                @endforeach
            </tr>
        </tbody>
    </table>

    <div class="section-title">Production Standard</div>
    <table>
        <thead>
            <tr><th>Line</th><th>Workers</th><th>{{ $report->capacity_label ?: 'Kapasitas/Speed' }}</th><th>Remark</th></tr>
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
                <tr><td colspan="4">Tidak ada data.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Line Configuration</div>
    <table>
        <thead>
            <tr><th>No</th><th>Equipment</th><th>Process</th><th>Worker</th><th>Trial</th><th>Remark</th></tr>
        </thead>
        <tbody>
            @forelse (($report->line_configuration ?? []) as $i => $row)
                <tr>
                    <td>{{ $row['no'] ?? $i + 1 }}</td>
                    <td>{{ $row['equipment'] ?? '-' }}</td>
                    <td>{{ $row['process'] ?? '-' }}</td>
                    <td>{{ $row['worker'] ?? '-' }}</td>
                    <td>{{ $row['trial_status'] ?? '-' }}</td>
                    <td>{{ $row['remark'] ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Tidak ada data.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($report->opinion)
        <div class="section-title">Opinion</div>
        <p>{{ $report->opinion }}</p>
    @endif
@endsection
