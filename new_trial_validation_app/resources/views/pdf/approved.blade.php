@extends('pdf.layout')

@section('content')
    <table>
        <thead>
            <tr>
                <th>Trial ID</th>
                <th>Product Name</th>
                <th>Finish Good Code</th>
                <th>Product Type</th>
                <th>Approved Date</th>
                <th>Approved By</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    <td>{{ $item['trial_code'] }}</td>
                    <td>{{ $item['product_name'] }}</td>
                    <td>{{ $item['finish_good_code'] }}</td>
                    <td>{{ $item['product_type'] }}</td>
                    <td>{{ $item['approved_at'] ?? '-' }}</td>
                    <td>{{ $item['approved_by'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="muted">Belum ada approved report.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
