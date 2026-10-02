<?php

// Dashboard and "My Work" page strings.
return [
    'title' => 'Dashboard Trial',
    'description' => 'Ringkasan sistem validasi trial: kondisi proses, tren, dan rincian data.',

    'kpi' => [
        'no_data' => 'Belum ada data',
        'approval_rate' => 'Tingkat Persetujuan',
        'approval_rate_caption' => 'dari trial yang telah diputuskan',
        'avg_approval_time' => 'Rata-rata Waktu Persetujuan',
        'avg_approval_time_caption' => 'sejak trial dibuat hingga disetujui',
        'days' => ':count hari',
        'active_trials' => 'Trial Aktif',
        'active_trials_caption' => 'trial yang sedang berjalan',
        'bottleneck' => 'Departemen Bottleneck',
        'bottleneck_none' => 'Tidak ada',
        'bottleneck_caption' => ':count tinjauan tertunda',
        'bottleneck_none_caption' => 'Seluruh tinjauan telah selesai',
    ],

    'summary' => [
        'total' => 'Total Trial',
        'total_breakdown' => 'Mixing: :mixing · Filling: :filling',
    ],

    'charts' => [
        'trend_title' => 'Tren Pembuatan Trial',
        'trend_series' => 'Trial Dibuat',
        'status_title' => 'Distribusi Status',
        'product_type_title' => 'Rincian per Product Type',
        'product_type_share_title' => 'Proporsi Trial per Product Type',
        'department_pending_title' => 'Tinjauan Tertunda per Departemen',
        'count_label' => 'Jumlah Trial',
        'other' => 'Lainnya',
        'empty_trials' => 'Belum ada trial.',
        'empty_pending_reviews' => 'Tidak ada tinjauan yang tertunda.',
    ],

    'filters' => [
        'search_placeholder' => 'Trial, produk, kode FG, kategori, cakupan, mesin',
        'search' => 'Pencarian',
        'status' => 'Status',
        'product_type' => 'Product Type',
        'trial_type' => 'Jenis Trial',
        'trial_type_placeholder' => 'Semua jenis trial',
        'date_from' => 'Tanggal Mulai',
        'date_to' => 'Tanggal Akhir',
        'chip' => ':label: :value',
        'empty' => 'Tidak ada trial yang sesuai dengan filter ini.',
    ],

    'my_work' => [
        'description' => 'Ringkasan tugas Anda: draft yang perlu dilanjutkan, trial yang perlu direvisi, trial yang sedang berjalan, serta tinjauan dan persetujuan yang menunggu tindakan Anda.',
        'drafts_title' => 'Draft Saya (Lanjutkan)',
        'drafts_empty' => 'Tidak ada draft yang perlu dilanjutkan.',
        'drafts_hint' => 'Klik Trial ID untuk melanjutkan pengisian formulir.',
        'revision_title' => 'Perlu Revisi',
        'revision_empty' => 'Tidak ada trial yang perlu direvisi.',
        'revision_hint' => 'Klik Trial ID untuk memperbaiki dan mengajukan ulang.',
        'in_progress_title' => 'Sedang Berjalan',
        'in_progress_empty' => 'Tidak ada trial Anda yang sedang berjalan.',
        'in_progress_hint' => 'Menunggu tinjauan/persetujuan. Saat ini tidak ada tindakan yang diperlukan dari Anda.',
        'reviews_title' => 'Perlu Tinjauan Saya',
        'reviews_empty' => 'Saat ini tidak ada tinjauan yang menunggu Anda.',
        'reviews_view_all' => 'Lihat semua yang perlu ditinjau',
        'approvals_title' => 'Perlu Persetujuan Saya',
        'approvals_empty' => 'Saat ini tidak ada trial yang menunggu persetujuan Anda.',
        'approvals_view_all' => 'Lihat semua yang perlu disetujui',
        'waiting' => 'Menunggu: :name',
        'more' => '+:count trial lainnya.',
        'last' => 'Terakhir: :code',
        'last_decided' => 'Terakhir: :code (:decision)',
    ],
];
