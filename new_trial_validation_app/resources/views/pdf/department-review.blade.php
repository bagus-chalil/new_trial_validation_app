@extends('pdf.layout')

@php($fmt = \App\Services\Pdf\ExportFormat::class)

@section('content')
    <table>
        <thead>
            <tr>
                <th>{{ __('report.info.trial_id') }}</th>
                <th>{{ __('report.info.product_name') }}</th>
                @foreach ($reviewerDepartments as $dept)
                    <th>{{ $dept }}</th>
                @endforeach
                <th>{{ __('exports.columns.review_status') }}</th>
                <th>{{ __('exports.columns.pending_department') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    <td>{{ $item['trial_code'] }}</td>
                    <td>{{ $item['product_name'] }}</td>
                    @foreach ($reviewerDepartments as $dept)
                        <td>{{ $fmt::reviewStatus($item['departments'][$dept] ?? 'N/A') }}</td>
                    @endforeach
                    <td>{{ $fmt::reviewStatus($item['review_status']) }}</td>
                    <td>{{ $item['pending_with'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($reviewerDepartments) + 4 }}" class="muted">{{ __('exports.empty.department_review') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
