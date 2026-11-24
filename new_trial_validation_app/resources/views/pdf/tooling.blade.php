@php
    $t = $tooling;
    $nl = fn (?string $v) => nl2br(e((string) $v));
    $letters = range('a', 'z');
    $choice = fn (?string $val, string $target) => $val === $target ? '<span class="circ">'.e($target).'</span>' : e($target);
    $date = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->locale('en')->translatedFormat('d F Y') : '';
    $cols = [
        ['prni', 'Prepared by', 'PRNI'],
        ['rni', 'Reviewed by', 'RNI'],
        ['pi', 'Reviewed by', 'PIE'],
        ['production', 'Reviewed by', 'Production'],
    ];
    $toolRows = $tools->isNotEmpty() ? $tools : collect([(object) ['tool' => '', 'actual' => '', 'status' => '']]);
    $n = $toolRows->count();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
    body { font: 11.5px/1.35 Calibri, Arial, 'Noto Sans CJK KR', sans-serif; color: #000; margin: 0; padding: 14px 20px; }
    table { border-collapse: collapse; width: 100%; }
    td, th { border: 1px solid #000; padding: 4px 7px; vertical-align: middle; }
    .no { width: 28px; text-align: center; font-weight: 700; }
    .lbl { width: 190px; font-weight: 600; }
    .c { text-align: center; }
    .side { background: #15803d; color: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; text-align: center; font-weight: 800; width: 36px; font-size: 10.5px; line-height: 1.15; }
    .subh { background: #15803d; color: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; font-weight: 700; text-align: center; }
    .chk { display: inline-block; width: 18px; height: 15px; border: 1px solid #000; text-align: center; font-weight: 800; line-height: 14px; }
    .circ { display: inline-block; border: 2px solid #000; border-radius: 999px; padding: 1px 8px; font-weight: 800; }
    .stamp { display: inline-block; border: 2px solid #15803d; color: #15803d; padding: 2px 7px; border-radius: 4px; font-size: 9.5px; font-weight: 800; line-height: 1.2; }
    .sig { height: 72px; text-align: center; padding: 4px; }
    .sig img { max-height: 54px; max-width: 140px; }
    @page { size: A4 portrait; margin: 10mm; }
</style>
</head>
<body>
<table>
  <tr>
    <td colspan="2" style="border-right:0;text-align:center;font-weight:800;font-size:15px;letter-spacing:1px">COSMAX</td>
    <td colspan="3" style="border-left:0;border-right:0;text-align:center;font-size:16px;font-weight:700">Packaging Proposal to Production Check</td>
    <td style="border-left:0;text-align:right;vertical-align:top;font-weight:700;font-size:10.5px">{{ $t->doc_code ?: 'FR.PRNI.002.00' }}</td>
  </tr>
  <tr>
    <td colspan="6" style="padding:6px 10px">
      <div style="font-weight:700;text-decoration:underline;margin-bottom:3px">NPD Request Number : {{ $t->npd_no }}</div>
      <div>Request date &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: {{ $date($t->request_date) }}</div>
      <div>Requested by &nbsp;&nbsp;&nbsp;: {{ $t->requested_by_name ?: $t->submitted_by_name }}</div>
    </td>
  </tr>
  <tr><td class="no">1</td><td class="lbl">Client code</td><td colspan="3">: {{ $t->client_code }}</td><td class="side" rowspan="12">PACK RNI</td></tr>
  <tr><td class="no">2</td><td class="lbl">Product name</td><td colspan="3">: {{ $t->product_name }}</td></tr>
  <tr><td class="no">3</td><td class="lbl">Marketing</td><td colspan="3">: {{ $t->marketing }}</td></tr>
  <tr><td class="no">4</td><td class="lbl">Packdev</td><td colspan="3">: {{ $t->packdev }}</td></tr>
  <tr><td class="no">5</td><td class="lbl">Product type</td><td colspan="3">: &nbsp;<span class="chk">{!! $t->product_type === 'skin_care' ? 'V' : '&nbsp;' !!}</span> skin care &nbsp;&nbsp;&nbsp;&nbsp;<span class="chk">{!! $t->product_type === 'make_up' ? 'V' : '&nbsp;' !!}</span> make up</td></tr>
  <tr><td class="no">6</td><td class="lbl">Packaging type</td><td colspan="3">: {{ $t->packaging_type }}</td></tr>
  <tr><td class="no">7</td><td class="lbl">Quantity PO</td><td colspan="3">: {{ $t->quantity_po }}</td></tr>
  <tr><td class="no">8</td><td class="lbl">Launch date</td><td colspan="3">: {{ $t->launch_date }}</td></tr>
  <tr><td class="no">9</td><td class="lbl">Dimension packaging</td><td colspan="3">: {{ $t->dimension_packaging ?: 'please see attachment' }}</td></tr>
  <tr><td class="no">10</td><td class="lbl">Material packaging / concept packaging</td><td colspan="3">: {{ $t->material_packaging ?: 'please see attachment' }}</td></tr>
  <tr><td class="no">11</td><td class="lbl">Bill of material primary</td><td colspan="3">: {{ $t->bom_primary }}</td></tr>
  <tr><td class="no">12</td><td class="lbl">PM reference picture and detail request</td><td colspan="3" style="height:60px">{!! $nl($t->pm_ref_detail) !!}</td></tr>
  <tr><td class="no">13</td><td class="lbl">Formula information</td><td colspan="3" style="height:48px">{!! $nl($t->formula_info) !!}</td><td class="side" rowspan="2">RNI</td></tr>
  <tr><td class="no">14</td><td class="lbl">Volume suggest</td><td colspan="3" style="height:28px">{{ $t->volume_suggest }}</td></tr>
  <tr><td colspan="2" style="border-bottom:0"></td><td class="subh">Tools</td><td class="subh">Actual</td><td class="subh">Status</td><td class="subh"></td></tr>
  @foreach ($toolRows as $i => $tool)
    <tr>
      @if ($i === 0)
        <td class="no" rowspan="{{ $n }}">15</td><td class="lbl" rowspan="{{ $n }}">Production tools machine</td>
      @endif
      <td style="width:130px">{{ $letters[$i] ?? ($i + 1) }}. {{ $tool->tool }}</td>
      <td>: {{ $tool->actual }}</td>
      <td class="c" style="width:90px"><b>{{ $tool->status }}</b></td>
      @if ($i === 0)
        <td class="side" rowspan="{{ $n + 4 }}">P<br>R<br>O<br>D<br>U<br>C<br>T<br>I<br>O<br>N</td>
      @endif
    </tr>
  @endforeach
  <tr><td class="no">16</td><td class="lbl">Recommended with machine / line</td><td colspan="3">: {{ $t->recommended_machine }}</td></tr>
  <tr><td class="no">17</td><td class="lbl">Need invest</td><td style="width:130px">: {!! $choice($t->need_invest, 'YES') !!} / {!! $choice($t->need_invest, 'NO') !!}</td><td colspan="2">: {{ $t->need_invest_note ?: ($t->need_invest === 'NO' ? 'No need invest' : '') }}</td></tr>
  <tr><td class="no">18</td><td class="lbl">Note from production team :</td><td colspan="3" style="height:52px">{!! $nl($t->production_note) !!}</td></tr>
  <tr><td class="no">19</td><td class="lbl">Review production</td><td colspan="3">: &nbsp;&nbsp; {!! $choice($t->risk_level, 'High risk') !!} / {!! $choice($t->risk_level, 'Medium risk') !!} / {!! $choice($t->risk_level, 'Low risk') !!}</td></tr>
  <tr>
    <td class="no">20</td>
    <td colspan="5" style="padding:0">
      <table style="border:0;width:100%"><tr>
        @foreach ($cols as [$role, $head, $foot])
          @php
              $ap = $approvals[$role] ?? null;
              $image = $ap?->signature_image;
              $image = is_string($image) && preg_match('#^data:image/(?:png|jpeg);base64,[A-Za-z0-9+/=]+$#', $image) === 1 ? $image : null;
          @endphp
          <td style="width:25%;text-align:center;padding:0">
            <div style="border-bottom:1px solid #000;padding:4px;font-weight:700">{{ $head }}</div>
            <div class="sig">
              @if ($ap?->approved)
                @if ($image)
                  <img src="{{ $image }}" alt=""><div style="font-size:9px;color:#333">{{ $ap->sign_date?->toDateString() }}</div>
                @else
                  <div class="stamp">{{ $role === 'prni' ? 'SUBMITTED' : 'REVIEWED' }}<br>{{ $ap->signer_name ?: $foot }}<br>{{ $ap->sign_date?->toDateString() }}</div>
                @endif
              @endif
            </div>
            <div style="border-top:1px solid #000;padding:5px;font-weight:700">{{ $foot }}{{ $ap?->approved && $ap->signer_name ? ' ('.$ap->signer_name.')' : '' }}</div>
          </td>
        @endforeach
      </tr></table>
    </td>
  </tr>
</table>
</body>
</html>
