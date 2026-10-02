<?php

return [
    'title' => 'Line Configuration Report',
    'dialog_title' => 'Line Configuration Report (Production)',
    'subtitle' => 'Production — konfigurasi line, standar produksi, dan sign-off :first → :second.',
    'empty' => 'Belum ada Line Configuration Report.',
    'no_data' => 'Tidak ada data.',

    'badge' => [
        'signed_off' => 'Selesai Sign-off',
        'in_approval' => 'Dalam Proses Approval',
    ],

    'actions' => [
        'edit' => 'Edit Report',
        'create' => 'Buat Line Configuration Report',
        'add_row' => 'Tambah Baris',
        'remove_row' => 'Hapus baris',
        'save' => 'Simpan Line Configuration Report',
        'approve' => 'Approve',
        'mark_checked' => 'Tandai Checked',
        'return' => 'Return',
        'download_pdf' => 'PDF',
    ],

    'return_note' => [
        'title' => 'Dikembalikan untuk Revisi',
        'approver' => 'Approver',
    ],

    'versions' => [
        'title' => 'Riwayat Versi',
        'hint' => 'Versi yang telah di-Return dikunci di sini — hanya dapat diunduh, tidak dapat diedit kembali.',
        'locked_at' => 'Dikunci :date',
    ],

    'sign_off' => [
        'title' => 'Sign-off',
        'select_user' => 'Pilih user...',
        'search_user' => 'Cari user...',
        'no_eligible_users' => 'Belum ada user yang memenuhi syarat tim untuk :lane. Atur melalui Access Rights / Lane Configuration.',
        'hint' => 'User yang dipilih akan menerima email, kemudian melakukan approve/checked sendiri di halaman ini (berjenjang — :second baru dapat dilakukan setelah :first selesai). Tanggal tercatat otomatis pada saat itu. Setelah salah satu di-assign, form ini terkunci (tidak dapat diedit) hingga di-Return.',
        'not_confirmed' => 'Belum dikonfirmasi.',
        'confirmed_by' => 'Dikonfirmasi oleh :name',
        'confirmed_by_at' => 'Dikonfirmasi oleh :name pada :date',
    ],

    'fields' => [
        'date' => 'Tanggal',
        'client' => 'Client',
        'validation' => 'Validation',
        'pic' => 'PIC',
        'operator' => 'Operator',
        'total' => 'Total',
        'setting' => 'Setting',
        'pass' => 'PASS',
        'ng' => 'NG',
        'ng_hint' => 'Dihitung otomatis dari Total − Setting − PASS (dapat diubah manual bila diperlukan).',
        'opinion' => 'Opini',
    ],

    'sections' => [
        'production_standard' => 'Production Standard',
        'line_configuration' => 'Line Configuration',
    ],

    'columns' => [
        'line' => 'Line',
        'workers' => 'Jumlah Pekerja',
        'capacity' => 'Kapasitas/Speed',
        'remark' => 'Keterangan',
        'no' => 'No',
        'equipment' => 'Peralatan',
        'process' => 'Proses',
        'worker' => 'Pekerja',
        'trial' => 'Trial',
    ],

    'total_workers' => 'Total Pekerja',
    'total_workers_inline' => 'Total pekerja:',

    'trial_status' => [
        'pass' => 'Pass',
        'no_trial' => 'No Trial',
    ],

    'stage' => [
        'prepared' => 'Prepared (PIE)',
        'not_filled' => 'Belum diisi',
        'not_assigned' => 'Belum ditentukan',
        'confirmed' => 'Dikonfirmasi',
        'waiting_for' => 'Menunggu :name',
        'awaiting_you' => 'Menunggu tindakan Anda',
        'assigned_to' => 'Ditugaskan kepada :name',
    ],

    'action_panel' => [
        'title' => 'Tindakan Diperlukan: :lane',
        'description' => 'Konfirmasi :lane untuk report ini, atau kembalikan untuk revisi.',
        'comment' => 'Komentar',
        'comment_placeholder' => 'Komentar (opsional untuk konfirmasi, wajib untuk Return)...',
        'min_words' => 'Return memerlukan minimal :count kata —',
    ],
];
