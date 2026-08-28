@extends('pdf.layout')

@section('content')
    <table>
        <thead>
            <tr>
                <th>Trial ID</th>
                <th>Product Name</th>
                @foreach ($reviewerDepartments as $dept)
                    <th>{{ $dept }}</th>
                @endforeach
                <th>Review Status</th>
                <th>Pending Department</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    <td>{{ $item['trial_code'] }}</td>
                    <td>{{ $item['product_name'] }}</td>
                    @foreach ($reviewerDepartments as $dept)
                        <td>{{ $item['departments'][$dept] ?? 'N/A' }}</td>
                    @endforeach
                    <td>{{ $item['review_status'] }}</td>
                    <td>{{ $item['pending_with'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($reviewerDepartments) + 4 }}" class="muted">Belum ada data review department.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
