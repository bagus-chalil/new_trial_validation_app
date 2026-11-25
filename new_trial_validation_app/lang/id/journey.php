<?php

// Trial Validation v2.0: Product Journey (Case) pages and related fields.
return [
    'title' => 'Product Journey',
    'description' => 'Satu jejak per produk: berapa kali ditrial, hasil tiap percobaan, dan dokumen yang terkait.',
    'search_placeholder' => 'Nomor Case, produk, atau kode FG',
    'all_types' => 'Semua jenis',
    'empty' => 'Belum ada Case yang sesuai.',

    'type' => [
        'NPD' => 'NPD',
        'IMP' => 'Improvement',
        'MIX' => 'Mixing',
    ],

    'result' => [
        'pass' => 'Pass',
        'pass_client' => 'Pass dengan Approval Client',
        'retrial' => 'Re-trial',
        'fail' => 'Fail',
        'pending' => 'Menunggu hasil',
    ],

    'columns' => [
        'case_no' => 'Nomor Case',
        'product' => 'Produk',
        'fg' => 'Kode FG',
        'type' => 'Jenis',
        'attempts' => 'Jumlah Trial',
        'last_result' => 'Hasil Terakhir',
    ],

    'summary' => [
        'attempts' => ':count kali trial',
        'none' => 'Belum ada trial yang diajukan',
        'last_result' => 'Hasil terakhir',
        'lead_time' => 'Rata-rata lead time',
        'lead_time_value' => ':days hari (:samples trial)',
        'lead_time_none' => 'Belum ada tanggal PM/Bulk ready yang diisi',
        'first_trial' => 'Trial pertama',
        'breakdown' => 'Rincian hasil',
        'fg_master_note' => 'Kode FG dibaca dari master produk.',
    ],

    'attempt' => [
        'title' => 'Percobaan ke-:no',
        'timeline' => 'Linimasa',
        'trial_date' => 'Tanggal trial',
        'decided_at' => 'Diputuskan',
        'pending_with' => 'Menunggu',
        'revision' => 'Revisi ke-:no',
        'pm_ready' => 'PM ready',
        'bulk_ready' => 'Bulk ready',
        'dates_defaulted' => 'Tanggal bawaan (H-1), tidak dihitung di lead time',
        'lead_time' => 'Lead time :days hari',
        'stale_fg' => 'Kode FG di trial usang; master dipakai',
        'has_not_ok' => 'Ada parameter NOT OK',
        'line_config' => 'Line Configuration: :status',
        'open_trial' => 'Buka laporan trial',
        'private' => 'Anda tidak punya akses ke trial ini',
    ],

    'line_config_status' => [
        'Draft' => 'Draft',
        'Submitted' => 'Dikirim',
        'Approved' => 'Disetujui',
    ],

    'client' => [
        'title' => 'Approval Client',
        'approved' => 'Client menyetujui',
        'note' => 'Catatan / bukti (nomor surat, nama, tanggal)',
        'evidence_hint' => 'Unggah dokumennya lewat lampiran tambahan trial, lalu catat rujukannya di sini.',
        'save' => 'Simpan',
        'clear' => 'Hapus approval',
        'edit' => 'Catat approval client',
    ],

    'projects' => [
        'title' => 'Project terkait',
        'empty' => 'Belum ada project yang tertaut ke Case ini.',
    ],

    'form' => [
        'pm_ready_date' => 'Tanggal PM Ready',
        'bulk_ready_date' => 'Tanggal Bulk Ready',
    ],

    'kpi' => [
        'title' => 'Realized Trial Lead Time (NPD)',
        'value' => ':days hari',
        'none' => 'Belum ada data',
        'note' => 'Tanggal trial dikurangi tanggal PM/Bulk ready terakhir, dari :samples trial NPD dengan tanggal asli.',
        'excluded' => ':count trial memakai tanggal bawaan dan tidak dihitung.',
    ],

    'toast' => [
        'client_approval_saved' => 'Approval client disimpan.',
    ],
];
