<?php

// Strings shared across the whole UI (navigation, header, statuses, generic
// actions). Page-specific strings live in their own group file.
return [
    'language' => 'Bahasa',

    'nav' => [
        'overview' => 'Ringkasan',
        'trials' => 'Trial',
        'results' => 'Hasil',
        'report' => 'Laporan',
        'master_data' => 'Master Data',
        'user_management' => 'Manajemen Pengguna',
        'system' => 'Sistem',
        'dashboard' => 'Dashboard',
        'my_work' => 'Pekerjaan Saya',
        'tracking' => 'Pemantauan Proses',
        'need_review' => 'Perlu Ditinjau',
        'need_approval' => 'Perlu Disetujui',
        'need_revision' => 'Perlu Revisi',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'reports' => 'Laporan',
        'products' => 'Produk',
        'parameters' => 'Parameter',
        'masters' => 'Master',
        'users' => 'Pengguna',
        'access_rights' => 'Hak Akses',
        'line_configuration' => 'Konfigurasi Line',
        'notifications' => 'Notifikasi',
        'trash' => 'Tempat Sampah',
        'activity_logs' => 'Log Aktivitas',
        'old_app' => 'Aplikasi Lama',
        'open_old_app' => 'Buka Aplikasi Lama',
    ],

    'user_menu' => [
        'settings' => 'Pengaturan',
        'logout' => 'Keluar',
    ],

    'breadcrumb' => [
        'trials' => 'Trial',
        'status_trials' => 'Trial :status',
        'new_trial' => 'Trial Baru',
        'edit_trial' => 'Ubah Formulir Trial',
        'validation' => 'Validasi',
        'attachments' => 'Lampiran',
        'review' => 'Peninjauan',
        'trial_report' => 'Laporan Trial',
        'trial_detail' => 'Detail Trial',
        'settings' => 'Pengaturan',
    ],

    // Workflow status values stored in trials_header.progress_status. The
    // stored values never change (shared with the legacy app); only the label.
    'status' => [
        'draft' => 'Draft',
        'in_review' => 'In Review',
        'ready_for_approval' => 'Ready for Approval',
        'approved' => 'Approved',
        'need_revision' => 'Need Revision',
        'rejected' => 'Rejected',
    ],

    'process' => [
        'revision_back_to_draft' => 'Revisi (kembali ke Draft)',
    ],

    'wizard' => [
        'header' => 'Informasi Header',
        'validation' => 'Validasi',
        'weighing_packaging' => 'Penimbangan (Packaging)',
        'weighing_filling' => 'Penimbangan (Filling)',
        'attachments' => 'Lampiran',
        'review' => 'Tinjau & Kirim',
        'completed' => 'Formulir selesai',
        'step_of' => 'Langkah :current dari :total',
    ],

    'actions' => [
        'search' => 'Cari',
        'reset' => 'Atur Ulang',
        'cancel' => 'Batal',
        'confirm' => 'Konfirmasi',
        'previous' => 'Sebelumnya',
        'next' => 'Berikutnya',
        'back' => 'Kembali',
        'view_all' => 'Lihat semua',
        'view_detail' => 'Lihat Detail',
    ],

    'filter' => [
        'search_placeholder' => 'Cari...',
        'all' => 'Semua :label',
        'active' => 'Filter aktif:',
        'remove' => 'Hapus filter :label',
    ],

    'combobox' => [
        'placeholder' => 'Pilih...',
        'search' => 'Cari...',
        'empty' => 'Tidak ada hasil.',
        'remove' => 'Hapus :label',
        'selected' => ':count dipilih',
    ],

    'image_preview' => [
        'view' => 'Lihat detail gambar :name',
        'zoom' => 'Perbesar gambar',
        'close_hint' => 'Klik di luar gambar atau tekan Escape untuk menutup.',
        'zoom_out' => 'Perkecil',
        'zoom_in' => 'Perbesar',
        'fit' => 'Pas',
        'fit_title' => 'Sesuaikan dengan layar',
        'reset' => 'Atur Ulang',
        'reset_title' => 'Kembalikan ke ukuran awal',
    ],

    'pagination' => [
        'summary' => 'Halaman :current dari :last (:total :items)',
    ],
];
