@extends('pdf.layout')

@php($fmt = \App\Services\Pdf\ExportFormat::class)

@section('content')
    <table>
        <thead>
            <tr>
                <th>{{ __('report.info.trial_id') }}</th>
                <th>{{ __('report.info.product_name') }}</th>
                <th>{{ __('exports.columns.fg_code') }}</th>
                <th>{{ __('report.info.product_type') }}</th>
                <th>{{ __('report.info.validation_scope') }}</th>
                <th>{{ __('report.info.machine_used') }}</th>
                <th>{{ __('report.header.status') }}</th>
                <th>{{ __('exports.columns.current_step') }}</th>
                <th>{{ __('report.info.created_by') }}</th>
                <th>{{ __('exports.columns.created_date') }}</th>
                <th>{{ __('report.header.pending_with') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    <td>{{ $item['trial_code'] }}</td>
                    <td>{{ $item['product_name'] }}</td>
                    <td>{{ $item['finish_good_code'] }}</td>
                    <td>{{ $item['product_type'] }}</td>
                    <td>{{ implode(', ', $item['validation_scope'] ?? []) }}</td>
                    <td>{{ implode(', ', $item['machine_used'] ?? []) }}</td>
                    <td>{{ $fmt::trialStatus($item['progress_status']) }}</td>
                    <td>{{ $item['current_step'] ?? '-' }}</td>
                    <td>{{ $item['created_by'] ?? '-' }}</td>
                    <td>{{ $fmt::dateTime($item['created_at'] ?? null) }}</td>
                    <td>{{ $item['pending_with'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="muted">{{ __('exports.empty.trial_summary') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
