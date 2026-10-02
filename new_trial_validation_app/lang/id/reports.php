<?php

// Reports hub (reports/index) and its 5 report list pages.
return [
    'index' => [
        'title' => 'Laporan',
        'description' => 'Pilih jenis laporan trial validation.',
    ],

    'approved' => [
        'title' => 'Laporan Approved',
        'card_description' => 'Daftar trial yang sudah approved.',
        'description' => 'Trial dengan status Approved.',
        'empty' => 'Belum ada approved report.',
    ],

    'rejected' => [
        'title' => 'Laporan Rejected',
        'card_description' => 'Daftar trial rejected atau need revision.',
        'description' => 'Trial yang ditolak final oleh Manager QAC.',
        'reason' => 'Alasan / Catatan Akhir',
        'empty' => 'Belum ada rejected report.',
    ],

    'trial_summary' => [
        'title' => 'Laporan Ringkasan Trial',
        'card_description' => 'Ringkasan semua trial dengan filter.',
        'description' => 'Ringkasan semua trial validation.',
        'current_step' => 'Tahap Saat Ini',
        'all_product_types' => 'Semua product type',
        'all_scopes' => 'Semua scope',
        'all_machines' => 'Semua mesin',
        'product_name_placeholder' => 'Nama produk',
        'empty' => 'Tidak ada data trial.',
        'steps' => [
            'header' => 'Informasi Header',
            'validation' => 'Validation',
            'weighing_packaging' => 'Weighing (Packaging)',
            'weighing_filling' => 'Weighing (Filling)',
            'attachment' => 'Lampiran',
            'review' => 'Review',
            'approval' => 'Approval',
        ],
    ],

    'department_review' => [
        'title' => 'Laporan Review Departemen',
        'card_description' => 'Progres review per departemen.',
        'description' => 'Progres review per departemen.',
        'review_status' => 'Status Review',
        'pending_department' => 'Departemen Tertunda',
        'empty' => 'Belum ada data review departemen.',
    ],

    'audit_print_log' => [
        'title' => 'Log Cetak Audit',
        'card_description' => 'Log print report jika tersedia.',
        'description' => 'Log aktivitas print report jika tersedia.',
        'printed_by' => 'Dicetak Oleh',
        'printed_at' => 'Dicetak Pada',
        'report_type' => 'Jenis Laporan',
        'empty' => 'Belum ada audit print log.',
        'item_label' => 'log',
    ],

    'actions' => [
        'view_report' => 'Lihat Laporan',
        'view_summary' => 'Lihat Ringkasan',
        'view_review' => 'Lihat Review',
    ],
];
