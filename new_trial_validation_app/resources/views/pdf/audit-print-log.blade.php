@extends('pdf.layout')

@section('content')
    <table>
        <thead>
            <tr>
                <th>Trial ID</th>
                <th>Printed By</th>
                <th>Printed At</th>
                <th>Report Type</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    <td>{{ $item['trial_code'] ?? '-' }}</td>
                    <td>{{ $item['user_email'] ?? '-' }}</td>
                    <td>{{ $item['created_at'] ?? '-' }}</td>
                    <td>{{ $item['report_type'] ?? 'Report' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="muted">Belum ada audit print log.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
