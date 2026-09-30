@extends('pdf.layout')

@section('content')
    @include('pdf.partials.line-configuration-report', ['report' => $report])
@endsection
