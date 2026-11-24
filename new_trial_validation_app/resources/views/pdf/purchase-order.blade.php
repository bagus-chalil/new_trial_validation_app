@php
    /** @var \App\Models\PurchaseOrder $po */
    $isImg = fn ($v) => is_string($v) && preg_match('#^data:image/(png|jpeg|jpg);base64,#', $v) === 1;
    $nl = fn ($v) => nl2br(e((string) $v));
    $currency = e($po->currency ?: 'IDR');
    $kv = function ($label, $value) use ($nl) {
        return filled($value) ? '<tr><td class="k">'.$label.'</td><td class="s">:</td><td>'.$nl($value).'</td></tr>' : '';
    };
    $info = collect([[$lb['pay'], $po->terms], [$lb['lead'], $po->lead_time], [$lb['deliv'], $po->delivery]])->filter(fn ($x) => filled($x[1]));
    $billingLines = preg_split('/\r\n|\r|\n/', trim((string) $t['billing']));
    $notice = collect(preg_split('/(?:\r\n|\r|\n)\s*(?:\r\n|\r|\n)/', (string) $t['notice']))->filter(fn ($p) => trim($p) !== '');
    $signers = collect($t['signers'])->filter(fn ($s) => filled($s['label']));
@endphp
<!DOCTYPE html>
<html lang="{{ $t['lang'] }}">
<head>
<meta charset="utf-8">
<title>{{ $po->po_no }}</title>
<style>
  body{font:12px/1.4 Cambria,Georgia,'Times New Roman',serif;color:#000;margin:0}
  table{border-collapse:collapse;width:100%}td,th{vertical-align:top}
  .code{text-align:right;font-size:10px;margin-bottom:6px}
  .logo{display:block;max-height:72px;max-width:240px;object-fit:contain;margin-bottom:4px}.cn{font-weight:700;font-size:11px}.ca{font-size:10px;line-height:1.35}
  .ttl{font-size:17px;font-weight:700;text-decoration:underline;margin:8px 0}
  .po-meta{margin-top:24px}
  .kv td{padding:2px 3px}.kv .k{width:90px}.s{width:10px}
  .bx{margin-top:8px;border:1px solid #000}.bx>tbody>tr>td{width:50%;padding:5px 8px;border-right:1px solid #000;font-size:11px}.bx>tbody>tr>td:last-child{border-right:0}
  .bx .kv td{padding:1px 3px;font-size:11px}.bx .kv .k{width:46px}
  .frame{margin-top:8px;border:1px solid #000;min-height:430px}
  .it{border-bottom:1px solid #000}.it th{border:1px solid #000;border-top:0;padding:4px 5px;font-size:11px;text-align:center}.it th:first-child{border-left:0}.it th:last-child{border-right:0}
  .it td{padding:4px 5px;font-size:11px}.it tbody td{border-left:1px solid #000;border-right:1px solid #000;border-bottom:1px solid #000}.it tbody td.nb{border:0}.it tbody td:first-child{border-left:0}.it tbody td:last-child{border-right:0}
  .c{text-align:center}.r{text-align:right}.lb{white-space:nowrap}.g td{font-weight:700}
  .info{margin:14px 12px 10px}.info td{padding:2px 3px;font-size:11px;font-weight:700}.info .ik{width:110px}
  .bill{margin:14px 12px 0;font-size:11px;line-height:1.5}
  .nt{margin:12px 12px 0;font-size:11px;white-space:pre-line}
  .notice{margin:14px 14px 0;text-align:center;font-weight:700;font-size:11px}.notice p{margin:0 0 10px}
  .sgs{width:auto;margin:18px 30px 14px auto}.sg{width:190px;text-align:center;font-size:11px}.sgi{height:62px;text-align:center}.sgi img{max-height:60px;max-width:170px}.sgl{border-top:1px solid #000;font-weight:700;margin-top:2px}
</style>
</head>
<body>
<table>
  <tr>
    <td style="width:46%">
      @if ($isImg($t['logo']))<img class="logo" src="{{ $t['logo'] }}" alt="">@endif
      <div class="cn">{{ $t['company'] }}</div>
      <div class="ca">{!! $nl($t['address']) !!}@if (filled($t['contact']))<br>{!! $nl($t['contact']) !!}@endif</div>
    </td>
    <td>
      <div class="code">{{ $t['doc_code'] }}</div>
      <div class="ttl">{{ $t['title'] }}</div>
      <table class="kv po-meta">{!! $kv($lb['po'], $po->po_no) !!}{!! $kv($lb['date'], $dateFmt($po->po_date)) !!}</table>
    </td>
  </tr>
</table>

<table class="bx">
  <tr>
    <td>
      <b>{{ $lb['to'] }}</b><br><b>{{ $po->vendor_name }}</b><br>{!! $nl($po->vendor_address) !!}
      <table class="kv">{!! $kv($lb['tel'], $po->tel) !!}{!! $kv($lb['attn'], $po->attn) !!}{!! $kv($lb['email'], $po->email) !!}</table>
    </td>
    <td>
      <b>{{ $lb['ship'] }}</b><br><b>{{ $po->ship_name }}</b><br>{!! $nl($po->ship_address) !!}
      <table class="kv">{!! $kv($lb['tel'], $po->ship_tel) !!}{!! $kv($lb['attn'], $po->ship_attn) !!}</table>
    </td>
  </tr>
</table>

<div class="frame">
  <table class="it">
    <thead>
      <tr>
        <th style="width:30px">{{ $lb['no'] }}</th>
        <th>{{ $lb['desc'] }}</th>
        <th style="width:40px">{{ $lb['qty'] }}</th>
        <th style="width:44px">{{ $lb['unit'] }}</th>
        <th style="width:70px">{{ $lb['due'] }}</th>
        <th style="width:92px">{{ $lb['price'] }}<br>{{ $currency }}</th>
        <th style="width:100px">{{ $lb['total'] }}<br>{{ $currency }}</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($po->items as $n => $item)
        <tr>
          <td class="c">{{ $n + 1 }}</td>
          <td>{!! $nl($item->description) !!}</td>
          <td class="c">{{ rtrim(rtrim(number_format((float) $item->qty, 2, '.', ''), '0'), '.') }}</td>
          <td class="c">{{ $item->unit }}</td>
          <td class="c">{{ $item->due_date ? $dateFmt($item->due_date) : '' }}</td>
          <td class="r">{{ $numberFmt((float) $item->unit_price) }}</td>
          <td class="r">{{ $numberFmt((float) $item->qty * (float) $item->unit_price) }}</td>
        </tr>
      @endforeach
      <tr><td colspan="5" class="nb"></td><td class="lb">{{ $lb['sub'] }}</td><td class="r">{{ $numberFmt($totals['sub']) }}</td></tr>
      @if ((float) $po->tax_percent > 0)
        <tr><td colspan="5" class="nb"></td><td class="lb">{{ $lb['tax'] }} {{ rtrim(rtrim(number_format((float) $po->tax_percent, 2, '.', ''), '0'), '.') }}%</td><td class="r">{{ $numberFmt($totals['tax']) }}</td></tr>
      @endif
      <tr class="g"><td colspan="5" class="nb"></td><td class="lb">{{ $lb['grand'] }}</td><td class="r">{{ $numberFmt($totals['total']) }}</td></tr>
    </tbody>
  </table>

  @if ($info->isNotEmpty())
    <table class="info">
      @foreach ($info as $row)
        <tr><td class="ik">{{ $row[0] }}</td><td class="s">:</td><td>{!! $nl($row[1]) !!}</td></tr>
      @endforeach
    </table>
  @endif

  @if (filled($po->note))<div class="nt">{{ $po->note }}</div>@endif

  @if (count($billingLines) && trim($billingLines[0]) !== '')
    <div class="bill"><b><u>{{ $billingLines[0] }}</u></b>@foreach (array_slice($billingLines, 1) as $line)<br>{{ $line }}@endforeach</div>
  @endif

  @if ($notice->isNotEmpty())
    <div class="notice">@foreach ($notice as $p)<p>{!! $nl(trim($p)) !!}</p>@endforeach</div>
  @endif

  @if ($signers->isNotEmpty())
    <table class="sgs">
      <tr>
        @foreach ($signers as $s)
          <td class="sg">
            <div>{{ $s['label'] }}</div>
            <div class="sgi">@if ($po->use_signature && $isImg($s['image']))<img src="{{ $s['image'] }}" alt="">@endif</div>
            <div class="sgl">{{ $s['name'] }}&nbsp;</div>
            <div>{{ $s['title'] }}</div>
          </td>
        @endforeach
      </tr>
    </table>
  @endif
</div>
</body>
</html>
