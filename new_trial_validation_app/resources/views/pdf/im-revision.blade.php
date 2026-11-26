<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'Segoe UI', Arial, 'Noto Sans CJK KR', 'Noto Sans KR', 'Malgun Gothic', sans-serif; font-size: 9px; color: #111; line-height: 1.35; }
    table { border-collapse: collapse; width: 100%; }
    td, th { border: 1px solid #555; padding: 2px 4px; vertical-align: top; }
    .head td { font-size: 9px; }
    .title { text-align: center; font-weight: 700; font-size: 14px; background: #bfe0f5; }
    .line { text-align: center; font-weight: 700; background: #f7d98b; }
    .docno { text-align: center; font-weight: 700; background: #fff35c; }
    .sec { background: #d9777f; color: #fff; font-weight: 700; text-align: center; padding: 2px; border: 1px solid #555; }
    .sub { background: #d9777f; color: #fff; font-weight: 700; padding: 2px 4px; border: 1px solid #555; border-top: 0; }
    .th { background: #bfe0f5; font-weight: 700; text-align: center; }
    .th2 { background: #f4c7a0; font-weight: 700; text-align: center; }
    .box { border: 1px solid #c0392b; padding: 4px 6px; margin: 4px 0; }
    .box h5 { margin: 0 0 2px; font-size: 9px; color: #c0392b; }
    .box ol, .box ul { margin: 0; padding-left: 14px; }
    .gallery { display: flex; flex-wrap: wrap; align-items: flex-start; gap: 8px; margin: 4px 0; }
    .gallery figure { margin: 0; text-align: center; flex: 0 1 auto; max-width: 31%; }
    .gallery img { max-width: 100%; max-height: 150px; object-fit: contain; }
    .gallery figcaption { margin-top: 3px; padding: 2px 4px; border: 1px solid #2b6cb0; background: #eaf3fb; color: #1a4d80; font-size: 8px; font-weight: 700; }
    .subhead { font-weight: 700; font-size: 10px; margin: 6px 0 2px; }
    .sig tr.sigcols td { background: #eee; font-weight: 700; font-size: 8px; height: auto; padding: 1px 4px; border-bottom: 0; }
    .pre { white-space: pre-line; }
    .page-break { page-break-before: always; }
    .num { text-align: right; white-space: nowrap; }
    .wm { position: fixed; top: 40%; left: 20%; font-size: 70px; color: rgba(200, 0, 0, .12); transform: rotate(-20deg); z-index: 0; }
    .sig td { height: 34px; }
    .sig tr.grp td { height: auto; padding: 3px 4px; }
    .grp { background: #333; color: #fff; font-weight: 700; }
    .muted { color: #666; }
    table.cols { border: 0; }
    table.cols > tbody > tr > td.col { width: 50%; border: 0; padding: 0 3px; }
    .sec.green { background: #7fbf6a; }
    .legal { margin: 2px 0; font-weight: 700; }
</style>
</head>
<body>
@if (! $isEffective)
    <div class="wm">{{ strtoupper($revision->status) }} — {{ __('im_master.pdf.not_effective') }}</div>
@endif

@php
    $fmt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d F Y') : '';
    $lines = fn ($text) => collect(preg_split('/\r\n|\r|\n/', (string) $text))->map(fn ($l) => trim($l))->filter()->values();
    $section = fn ($key) => $params->get($key, collect());
    $nameOf = fn ($row) => $row->name.($row->description ? ' — '.$row->description : '');
    // All pictures of one category sit side by side in one row, each with its blue note box.
    $gallery = function (array $imgs) {
        if (empty($imgs)) {
            return '';
        }
        $html = '<div class="gallery">';
        foreach ($imgs as $img) {
            $html .= '<figure><img src="'.e($img['src']).'" alt="">'.(trim((string) $img['caption']) !== '' ? '<figcaption>'.e($img['caption']).'</figcaption>' : '').'</figure>';
        }

        return new \Illuminate\Support\HtmlString($html.'</div>');
    };
@endphp

{{-- The header block repeats on every page, like the paper IM --}}

@foreach ([1, 2, 3] as $page)
    <div @if (! $loop->first) class="page-break" @endif>
        <table class="head">
            <tr>
                <td style="width:16%"><strong>PT COSMAX INDONESIA</strong></td>
                <td class="title" style="width:34%">INSTRUKSI MUTU</td>
                <td style="width:16%">{{ __('im_master.pdf.customer_ref') }}: {{ $revision->customer_ref }}</td>
                <td class="line" colspan="2">{{ $revision->line_label }}</td>
            </tr>
            <tr>
                <td colspan="2" rowspan="3">
                    <div><strong>{{ __('im_master.pdf.description') }}</strong> : {{ $revision->description }}</div>
                    <div><strong>{{ __('im_master.pdf.fg_code') }}</strong> : {{ $master->fg_code }}@if (! empty($master->bulk_wip_refs)) ({{ implode(', ', $master->bulk_wip_refs) }})@endif
                        &nbsp;&nbsp; <strong>Mfg.category</strong> : {{ $revision->mfg_category }}</div>
                    <div><strong>{{ __('im_master.pdf.appearance') }}</strong> : {{ $revision->appearance }}
                        &nbsp;&nbsp; <strong>Customer</strong> : {{ $revision->customer }}</div>
                </td>
                <td><strong>{{ __('im_master.pdf.issue_date') }}</strong> : {{ $fmt($revision->issue_date) }}</td>
                <td style="width:16%"><strong>{{ __('im_master.pdf.replaces') }}</strong></td>
                <td class="docno" rowspan="3" style="width:18%">
                    {{ __('im_master.pdf.doc_no') }}<br>{{ $revision->im_number }}<br>
                    <span class="muted">{{ __('im_master.pdf.page', ['n' => $page, 'total' => 3]) }}</span>
                </td>
            </tr>
            <tr>
                <td rowspan="2"><strong>{{ __('im_master.pdf.revision_reason') }}</strong> : {{ $revision->revision_reason }}</td>
                <td rowspan="2" style="text-align:center">{{ $revision->replaces_number }}</td>
            </tr>
        </table>

        @if ($page === 1)
            {{-- Two columns like the paper IM: A.1 / points / A.3 on the left, A.2 and B on the right --}}
            <table class="cols"><tr>
                <td class="col">
                    <div class="sec">A. {{ __('im_master.pdf.fg_spec') }}</div>
                    <div class="sub">A.1 {{ __('im_master.pdf.appearance_filling') }}</div>
                    <table>
                        <tr class="th"><td style="width:22%">{{ __('im_master.pdf.item_no') }}</td><td>{{ __('im_master.pdf.packaging_material') }}</td><td style="width:20%">{{ __('im_master.pdf.category') }}</td></tr>
                        @forelse ($section('bom') as $row)
                            <tr><td>{{ $row->code }}</td><td>{{ $row->name }}</td><td>{{ $row->category }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="muted">—</td></tr>
                        @endforelse
                    </table>
                    <div class="box"><h5>{{ __('im_master.pdf.point_filling') }}</h5>
                        <ol>@foreach ($section('filling_point') as $row)<li>{{ $nameOf($row) }}</li>@endforeach</ol></div>
                    <div class="box"><h5>{{ __('im_master.pdf.point_appearance') }}</h5>
                        <ol>@foreach ($section('appearance_point') as $row)<li>{{ $nameOf($row) }}</li>@endforeach</ol></div>
                    {{ $gallery($images['appearance_filling'] ?? []) }}

                    <div class="sub">A.3 {{ __('im_master.pdf.weight_filling') }}</div>
                    @if (! empty($content['legal_note']))<p class="legal">{{ $content['legal_note'] }}</p>@endif
                    <table>
                        <tr class="th"><td>{{ __('im_master.pdf.product_name') }}</td><td>{{ __('im_master.pdf.std_weight') }}</td><td>{{ __('im_master.pdf.bulk_code') }}</td><td>{{ __('im_master.pdf.fg_wip_code') }}</td></tr>
                        @foreach ($section('weight') as $row)
                            <tr>
                                <td>{{ $row->name }}</td>
                                <td style="text-align:center"><strong>{{ rtrim(rtrim((string) $row->value_min, '0'), '.') }} - {{ rtrim(rtrim((string) $row->value_max, '0'), '.') }} {{ $row->unit }}</strong></td>
                                <td style="text-align:center">{{ $row->code }}</td>
                                <td style="text-align:center">{{ $row->category }}</td>
                            </tr>
                        @endforeach
                    </table>
                </td>
                <td class="col">
                    <div class="sec">A.2 {{ __('im_master.pdf.appearance_packing') }}</div>
                    {{ $gallery($images['primary_packaging'] ?? []) }}
                    <div class="box"><h5>{{ __('im_master.pdf.point_batch') }}</h5>
                        <ul>@foreach ($section('coding') as $row)<li><strong>{{ $row->name }}</strong>@if ($row->description) : {{ $row->description }}@endif @if ($row->specification) — {{ $row->specification }}@endif</li>@endforeach</ul>
                        @foreach (['batch_format_tube' => 'Tube', 'batch_format_unit_box' => 'Unit Box', 'batch_format_master_box' => 'Master Box'] as $key => $label)
                            @if (! empty($content[$key]))<div><strong>{{ $label }}</strong>: <span class="pre">{{ $content[$key] }}</span></div>@endif
                        @endforeach
                    </div>

                    <div class="sec green" style="margin-top:6px">B. {{ __('im_master.pdf.surveillance') }}</div>
                    @foreach (['surveillance_initial' => 'initial', 'surveillance_periodic' => 'periodic'] as $key => $label)
                        @php
                            $rows = $section($key)->values();
                            // Merge consecutive identical frequencies into one tall cell, like the paper sheet.
                            $spans = [];
                            $head = 0;
                            foreach ($rows as $i => $row) {
                                $freq = trim((string) $row->frequency);
                                if ($i > 0 && $freq !== '' && $freq === trim((string) $rows[$i - 1]->frequency)) {
                                    $spans[$i] = 0;
                                    $spans[$head]++;
                                } else {
                                    $spans[$i] = 1;
                                    $head = $i;
                                }
                            }
                        @endphp
                        <table>
                            <tr class="th2"><td style="width:30%">{{ __('im_master.pdf.check_'.$label) }}</td><td>{{ __('im_master.pdf.work_description') }}</td><td style="width:14%">{{ __('im_master.pdf.specification') }}</td><td style="width:24%">{{ __('im_master.pdf.frequency') }}</td></tr>
                            @foreach ($rows as $i => $row)
                                <tr><td>{{ $i + 1 }}. {{ $row->name }}</td><td>{{ $row->description }}</td><td>{{ $row->specification }}</td>
                                    @if (($spans[$i] ?? 1) > 0)<td rowspan="{{ $spans[$i] ?? 1 }}" style="vertical-align:middle;text-align:center">{{ $row->frequency }}</td>@endif
                                </tr>
                            @endforeach
                        </table>
                    @endforeach
                </td>
            </tr></table>
        @endif

        @if ($page === 2)
            <div class="sec">A. {{ __('im_master.pdf.fg_spec') }}</div>
            <div class="sub">A.4 {{ __('im_master.pdf.appearance_packing') }}</div>
            @foreach (['secondary_packaging' => 'secondary', 'tertiary_packaging' => 'tertiary'] as $cat => $label)
                @if (! empty($images[$cat]))
                    <div class="subhead">{{ __('im_master.pdf.packing_'.$label) }}</div>
                    {{ $gallery($images[$cat]) }}
                @endif
            @endforeach
            <div class="box"><h5>{{ __('im_master.pdf.packing_process') }}</h5>
                <ul>@foreach ($section('packing_point') as $row)<li>{{ $nameOf($row) }}</li>@endforeach</ul></div>
            <table>
                @if (! empty($content['master_box_qty']))<tr><td style="width:25%"><strong>{{ __('im_master.pdf.master_box') }}</strong></td><td>{{ $content['master_box_qty'] }}</td></tr>@endif
                @if (! empty($content['shipper_format']))<tr><td><strong>{{ __('im_master.pdf.shipper') }}</strong></td><td class="pre">{{ $content['shipper_format'] }}</td></tr>@endif
                @if (! empty($content['pallet_layout']))<tr><td><strong>{{ __('im_master.pdf.pallet') }}</strong></td><td>{{ $content['pallet_layout'] }}</td></tr>@endif
                @if (! empty($content['notes']))<tr><td><strong>{{ __('im_master.pdf.notes') }}</strong></td><td class="pre">{{ $content['notes'] }}</td></tr>@endif
            </table>
        @endif

        @if ($page === 3)
            @php
                $fixedReview = (array) config('im_signoff.review_labels', []);
                $extraReviews = $reviews->reject(fn ($r) => in_array($r->role_label, $fixedReview, true));
            @endphp
            <div class="sec">VALIDATION</div>
            <table class="sig">
                <tr class="grp"><td colspan="4">PREPARED BY</td></tr>
                <tr class="sigcols"><td>{{ __('im_master.pdf.col_name') }}</td><td>Signature</td><td>Date</td><td>Remarks</td></tr>
                <tr><td style="width:24%">{{ $prepared?->user_name ?? $revision->prepared_by_name }}<br><span class="muted">{{ $prepared?->role_label ?: config('im_signoff.prepared_label') }}</span></td>
                    <td style="width:24%">@if ($prepared?->status === 'Signed')<strong>{{ __('im_master.pdf.signed_electronically') }}</strong>@endif</td>
                    <td style="width:16%">{{ $prepared?->signed_at?->format('d-m-Y H:i') }}</td><td>{{ $prepared?->comment }}</td></tr>
                <tr class="grp"><td colspan="4">REVIEW BY</td></tr>
                <tr class="sigcols"><td>{{ __('im_master.pdf.col_name') }}</td><td>Signature</td><td>Date</td><td>Remarks</td></tr>
                @foreach ($fixedReview as $label)
                    @php $r = $reviews->firstWhere('role_label', $label); @endphp
                    <tr><td>{{ $r?->user_name }}<br><span class="muted">{{ $label }}</span></td>
                        <td>@if ($r?->status === 'Signed')<strong>{{ __('im_master.pdf.signed_electronically') }}</strong>@endif</td>
                        <td>{{ $r?->signed_at?->format('d-m-Y H:i') }}</td><td class="pre">{{ $r?->comment }}</td></tr>
                @endforeach
                @foreach ($extraReviews as $r)
                    <tr><td>{{ $r->user_name }}<br><span class="muted">{{ $r->role_label }}</span></td>
                        <td>@if ($r->status === 'Signed')<strong>{{ __('im_master.pdf.signed_electronically') }}</strong>@endif</td>
                        <td>{{ $r->signed_at?->format('d-m-Y H:i') }}</td><td class="pre">{{ $r->comment }}</td></tr>
                @endforeach
                <tr class="grp"><td colspan="4">KNOWLEDGE BY</td></tr>
                <tr class="sigcols"><td>{{ __('im_master.pdf.col_name') }}</td><td>Signature</td><td>Date</td><td>Remarks</td></tr>
                @foreach ($knowledgeSlots as $slot)
                    @php $k = $knowledge->get($slot['label']); @endphp
                    <tr><td>{{ $k?->user_name }}<br><span class="muted">{{ $slot['label'] }}</span></td>
                        <td>@if ($k?->status === 'Signed')<strong>{{ __('im_master.pdf.signed_electronically') }}</strong>@endif</td>
                        <td>{{ $k?->signed_at?->format('d-m-Y H:i') }}</td><td class="pre">{{ $k?->comment }}</td></tr>
                @endforeach
                <tr class="grp"><td colspan="4">APPROVED BY</td></tr>
                <tr class="sigcols"><td>{{ __('im_master.pdf.col_name') }}</td><td>Signature</td><td>Date</td><td>Remarks</td></tr>
                <tr><td>{{ $approval?->user_name }}<br><span class="muted">{{ $approval?->role_label ?: config('im_signoff.approved_label') }}</span></td>
                    <td>@if ($approval?->status === 'Signed')<strong>{{ __('im_master.pdf.signed_electronically') }}</strong>@endif</td>
                    <td>{{ $approval?->signed_at?->format('d-m-Y H:i') }}</td><td class="pre">{{ $approval?->comment }}</td></tr>
                <tr><td>Customer / Marketing</td><td></td><td></td><td></td></tr>
            </table>
            <p class="muted" style="margin-top:6px">{{ __('im_master.pdf.paper_note') }}</p>
        @endif
    </div>
@endforeach
</body>
</html>
