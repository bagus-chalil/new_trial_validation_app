@extends('pdf.layout')

@section('content')
    <table>
        <thead>
            <tr>
                <th>Trial ID</th>
                <th>Product Name</th>
                <th>FG Code</th>
                <th>Product Type</th>
                <th>Validation Scope</th>
                <th>Machine Used</th>
                <th>Status</th>
                <th>Current Step</th>
                <th>Created By</th>
                <th>Created Date</th>
                <th>Pending With</th>
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
                    <td>{{ $item['progress_status'] }}</td>
                    <td>{{ $item['current_step'] ?? '-' }}</td>
                    <td>{{ $item['created_by'] ?? '-' }}</td>
                    <td>{{ $item['created_at'] ?? '-' }}</td>
                    <td>{{ $item['pending_with'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="muted">Tidak ada data trial.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
