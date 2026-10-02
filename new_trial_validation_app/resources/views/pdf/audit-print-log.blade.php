@extends('pdf.layout')

@php($fmt = \App\Services\Pdf\ExportFormat::class)

@section('content')
    <table>
        <thead>
            <tr>
                <th>{{ __('report.info.trial_id') }}</th>
                <th>{{ __('exports.columns.printed_by') }}</th>
                <th>{{ __('exports.columns.printed_at') }}</th>
                <th>{{ __('exports.columns.report_type') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    <td>{{ $item['trial_code'] ?? '-' }}</td>
                    <td>{{ $item['user_email'] ?? '-' }}</td>
                    <td>{{ $fmt::dateTime($item['created_at'] ?? null) }}</td>
                    <td>{{ $item['report_type'] ?? __('exports.default_report_type') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="muted">{{ __('exports.empty.audit_print_log') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
