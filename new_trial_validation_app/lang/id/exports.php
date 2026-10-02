<?php

// PDF (resources/views/pdf/*) and Excel exports. On-screen terms are reused
// from report.php / line_config.php; only export-specific text lives here.
return [
    // Carbon translatedFormat() patterns (App\Services\Pdf\ExportFormat).
    'formats' => [
        'datetime' => 'j F Y H:i',
        'date' => 'j M Y',
    ],

    'titles' => [
        'approved' => 'Laporan Approved',
        'rejected' => 'Laporan Rejected',
        'trial_summary' => 'Laporan Ringkasan Trial',
        'department_review' => 'Laporan Review Departemen',
        'audit_print_log' => 'Log Audit Cetak',
        'line_config_version' => 'Line Configuration Report — :code (v:version)',
    ],

    'columns' => [
        'fg_code' => 'Kode FG',
        'approved_date' => 'Tanggal Approved',
        'rejected_date' => 'Tanggal Rejected',
        'reason' => 'Alasan / Catatan Akhir',
        'printed_by' => 'Dicetak Oleh',
        'printed_at' => 'Dicetak Pada',
        'report_type' => 'Jenis Laporan',
        'review_status' => 'Status Review',
        'pending_department' => 'Departemen Tertunda',
        'current_step' => 'Tahap Saat Ini',
        'created_date' => 'Tanggal Dibuat',
    ],

    'empty' => [
        'approved' => 'Belum ada laporan approved.',
        'rejected' => 'Belum ada laporan rejected.',
        'trial_summary' => 'Tidak ada data trial.',
        'department_review' => 'Belum ada data review departemen.',
        'audit_print_log' => 'Belum ada log audit cetak.',
    ],

    'default_report_type' => 'Laporan',

    'attachments' => [
        'file_not_found' => 'File tidak ditemukan',
        'pdf_listed_only' => 'File PDF — unduh dari halaman Report',
    ],

    'line_config' => [
        'version' => 'Versi :version',
        'locked' => 'Terkunci (hanya baca)',
        'latest' => 'Terbaru',
        'return_prod' => 'Return (PROD)',
        'yes' => 'Ya',
    ],

    'excel' => [
        'sheets' => [
            'info' => 'Info Trial',
            'validation' => 'Validasi',
            'weighing' => 'Penimbangan',
            'review' => 'Review Departemen',
            'decision' => 'Keputusan',
        ],
        'info_title' => 'Informasi Trial',
        'section' => 'Bagian',
        'reviewer' => 'Reviewer',
        'no_data' => 'Tidak ada data.',
    ],
];
