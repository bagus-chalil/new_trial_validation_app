@extends('pdf.layout')

@section('content')
    <table>
        <thead>
            <tr>
                <th>Trial ID</th>
                <th>Product Name</th>
                <th>Finish Good Code</th>
                <th>Product Type</th>
                <th>Rejected Date</th>
                <th>Rejected By</th>
                <th>Reason / Final Remark</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    <td>{{ $item['trial_code'] }}</td>
                    <td>{{ $item['product_name'] }}</td>
                    <td>{{ $item['finish_good_code'] }}</td>
                    <td>{{ $item['product_type'] }}</td>
                    <td>{{ $item['rejected_at'] ?? '-' }}</td>
                    <td>{{ $item['rejected_by'] ?? '-' }}</td>
                    <td>{{ $item['approval_comment'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="muted">Belum ada rejected report.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
