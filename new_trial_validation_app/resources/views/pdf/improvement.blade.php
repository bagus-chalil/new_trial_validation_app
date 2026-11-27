@php
    $i = $imp;
    $nl = fn (?string $v) => nl2br(e((string) $v));
    $date = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->locale('en')->translatedFormat('d F Y') : '';
    $decisionText = match ($i->decision) {
        'To Project' => 'Proses ke Project PIE',
        'Suggestion Only' => 'Saran Alternatif',
        default => 'Menunggu',
    };
    $approved = in_array($i->status, ['Disetujui', 'Selesai'], true);
    $fileList = fn ($list) => $list->isEmpty()
        ? '<span style="color:#64748b">Tidak ada lampiran</span>'
        : $list->map(fn ($f) => '<div><b>'.e($f->file_name).'</b>'.($f->description ? ' &mdash; '.e($f->description) : '').'</div>')->implode('');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
    body { font: 11.5px/1.4 Calibri, Arial, 'Noto Sans CJK KR', sans-serif; color: #000; margin: 0; padding: 14px 20px; }
    table { border-collapse: collapse; width: 100%; }
    td, th { border: 1px solid #000; padding: 5px 8px; vertical-align: top; }
    .lbl { width: 190px; font-weight: 600; background: #f1f5f9; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .sec { background: #15803d; color: #fff; font-weight: 800; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .stamp { display: inline-block; border: 2px solid #15803d; color: #15803d; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 800; line-height: 1.25; }
    @page { size: A4 portrait; margin: 10mm; }
</style>
</head>
<body>
<table>
  <tr>
    <td style="width:170px;padding:8px 10px"><div style="font-size:20px;font-weight:900;letter-spacing:.06em">COSMAX</div></td>
    <td style="text-align:center;vertical-align:middle;font-size:15px;font-weight:800">FORM PENGAJUAN &amp; EVALUASI IMPROVEMENT (PIE TEAM)</td>
    <td style="width:150px;text-align:right;font-weight:700;font-size:10.5px">FR.IMP.001.00<br><span style="font-weight:400">Status: <b>{{ $i->status }}</b></span></td>
  </tr>
</table>
<table style="margin-top:-1px">
  <tr><td colspan="4" class="sec">A. IDENTITAS PENGAJUAN IMPROVEMENT (KONTRIBUTOR)</td></tr>
  <tr><td class="lbl">Judul Improvement</td><td colspan="3"><b>{{ $i->title }}</b></td></tr>
  <tr><td class="lbl">Departemen Pengaju</td><td>{{ $i->department }}</td><td class="lbl">Pelapor / Tanggal</td><td>{{ $i->submitted_by_name }} ({{ $date($i->submitted_at) }})</td></tr>
  <tr><td class="lbl">Kategori Improvement</td><td>{{ $i->type }}</td><td class="lbl">Target Metrik / Saving</td><td>{{ $i->saving_mp ?: ($i->quality_metric ?: '-') }}</td></tr>
  <tr><td colspan="4" class="sec">B. RINCIAN MASALAH, KONDISI SAAT INI &amp; TUJUAN</td></tr>
  <tr><td class="lbl">1. Kendala / Issue</td><td colspan="3" style="height:42px">{!! $nl($i->issue) !!}</td></tr>
  <tr><td class="lbl">2. Kondisi Saat Ini (Background)</td><td colspan="3" style="height:42px">{!! $nl($i->background) !!}</td></tr>
  <tr><td class="lbl">3. Alasan (Purpose)</td><td colspan="3" style="height:36px">{!! $nl($i->purpose) !!}</td></tr>
  <tr><td class="lbl">4. Target Hasil (Goals)</td><td colspan="3" style="height:36px">{!! $nl($i->goals) !!}</td></tr>
  <tr><td class="lbl">5. Foto / Video / Dokumen &amp; Keterangan</td><td colspan="3">{!! $fileList($files) !!}</td></tr>
  <tr><td colspan="4" class="sec">C. HASIL TINJAUAN &amp; KEPUTUSAN TIM PIE</td></tr>
  <tr><td class="lbl">Status &amp; Keputusan PIE</td><td><b>{{ $i->status }}</b> ({{ $decisionText }})</td><td class="lbl">PIC Review Tim PIE</td><td>{{ $i->pi_owner ?: '-' }}</td></tr>
  <tr><td class="lbl">Feedback Analisa Tim PIE</td><td colspan="3" style="height:44px">{!! $nl($i->pi_feedback) !!}</td></tr>
  <tr><td class="lbl">Action Plan / Solusi</td><td colspan="3" style="height:36px">{!! $nl($i->pi_action ?: $i->pi_suggestion) !!}</td></tr>
  @if ($i->pi_reason)
    <tr><td class="lbl">Catatan / Alasan</td><td colspan="3">{!! $nl($i->pi_reason) !!}</td></tr>
  @endif
  @if ($project)
    <tr><td class="lbl">Tindak Lanjut Project PIE</td><td colspan="3"><b>{{ $project->code }} &mdash; {{ $project->name }}</b> (PIC: {{ $project->pic_name ?: '-' }}, Status: {{ $project->status }}, Progres: {{ $project->progress }}%)</td></tr>
  @endif
  @if ($i->after_condition || $i->after_result || $afterFiles->isNotEmpty())
    <tr><td colspan="4" class="sec">D. HASIL &amp; DOKUMENTASI PERBAIKAN (RESULT)</td></tr>
    <tr><td class="lbl">Kondisi Setelah Perbaikan</td><td colspan="3">{!! $nl($i->after_condition) !!}</td></tr>
    <tr><td class="lbl">Hasil</td><td colspan="3">{!! $nl($i->after_result) !!}</td></tr>
    <tr><td class="lbl">Tanggal Hasil</td><td>{{ $date($i->after_date) }}</td><td class="lbl">PIC Hasil</td><td>{{ $i->after_pic ?: '-' }}</td></tr>
    <tr><td class="lbl">Bukti Hasil</td><td colspan="3">{!! $fileList($afterFiles) !!}</td></tr>
  @endif
  <tr>
    <td colspan="2" style="width:50%;text-align:center;padding:8px">
      <div style="font-weight:700;margin-bottom:6px">Diajukan Oleh (Kontributor)</div>
      <div style="height:64px"><div class="stamp">SUBMITTED<br>{{ $i->submitted_by_name }}</div></div>
      <div style="font-weight:700;margin-top:4px">{{ $i->submitted_by_name }} ({{ $i->department }})</div>
    </td>
    <td colspan="2" style="width:50%;text-align:center;padding:8px">
      <div style="font-weight:700;margin-bottom:6px">Ditinjau &amp; Disetujui Oleh (Tim PIE)</div>
      <div style="height:64px">
        @if ($approved)
          <div class="stamp">APPROVED<br>{{ $i->pi_owner ?: ($i->updated_by_name ?: 'PIE TEAM') }}</div>
        @else
          <span style="color:#666;font-size:10px">Menunggu Keputusan</span>
        @endif
      </div>
      <div style="font-weight:700;margin-top:4px">{{ $i->pi_owner ?: 'Tim PIE' }}</div>
    </td>
  </tr>
</table>
</body>
</html>
