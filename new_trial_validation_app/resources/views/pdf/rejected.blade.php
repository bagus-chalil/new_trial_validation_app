@extends('pdf.layout')

@php($fmt = \App\Services\Pdf\ExportFormat::class)

@section('content')
    <table>
        <thead>
            <tr>
                <th>{{ __('report.info.trial_id') }}</th>
                <th>{{ __('report.info.product_name') }}</th>
                <th>{{ __('report.info.fg_code') }}</th>
                <th>{{ __('report.info.product_type') }}</th>
                <th>{{ __('exports.columns.rejected_date') }}</th>
                <th>{{ __('report.decision.rejected_by') }}</th>
                <th>{{ __('exports.columns.reason') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    <td>{{ $item['trial_code'] }}</td>
                    <td>{{ $item['product_name'] }}</td>
                    <td>{{ $item['finish_good_code'] }}</td>
                    <td>{{ $item['product_type'] }}</td>
                    <td>{{ $fmt::dateTime($item['rejected_at'] ?? null) }}</td>
                    <td>{{ $item['rejected_by'] ?? '-' }}</td>
                    <td>{{ $item['approval_comment'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="muted">{{ __('exports.empty.rejected') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
