<?php

// Trial lists (per-status + tracking), the shared trials table, and the
// Need Review / Need Approval queues.
return [
    'table' => [
        'trial_code' => 'Kode Trial',
        'product_name' => 'Nama Produk',
        'finish_good_code' => 'Finish Good Code',
        'product_type' => 'Product Type',
        'validation_scope' => 'Jenis Trial',
        'status' => 'Status',
        'progress' => 'Progres',
        'created_at' => 'Tanggal Dibuat',
        'pending_with' => 'Menunggu Tindakan',
        'actions' => 'Aksi',
        'edit' => 'Ubah',
        'empty' => 'Tidak ada trial yang sesuai dengan filter ini.',
        'item_label' => 'trial',
    ],

    'list' => [
        'new_trial' => 'Trial Baru',
        'search_placeholder' => 'Kode trial, produk, kode FG, jenis trial, mesin',
        'empty' => 'Tidak ada trial pada halaman ini.',
        'filters' => [
            'search' => 'Pencarian',
            'status' => 'Status',
            'product_type' => 'Product Type',
            'validation_scope' => 'Jenis Trial',
            'all_validation_scopes' => 'Semua jenis trial',
            'date_from' => 'Tanggal Dari',
            'date_to' => 'Tanggal Sampai',
            'chip_from' => 'Dari',
            'chip_to' => 'Sampai',
        ],
        'groups' => [
            'approved' => [
                'title' => 'Trial Approved',
                'subtitle' => 'Daftar trial yang telah disetujui.',
            ],
            'tracking' => [
                'title' => 'Pemantauan Proses',
                'subtitle' => 'Pantau seluruh trial yang sedang berjalan (In Review dan Ready for Approval). Halaman ini hanya untuk pemantauan; tindakan review dan approval dilakukan melalui menu Perlu Ditinjau dan Perlu Disetujui.',
            ],
            'need_revision' => [
                'title' => 'Trial Need Revision',
                'subtitle' => 'Trial yang dikembalikan kepada Staff untuk direvisi.',
            ],
            'rejected' => [
                'title' => 'Trial Rejected',
                'subtitle' => 'Trial yang telah ditolak secara final.',
            ],
            'draft' => [
                'title' => 'Trial Draft',
                'subtitle' => 'Trial yang masih berstatus draft.',
            ],
        ],
    ],

    'queue' => [
        'search_placeholder' => 'Cari kode trial atau produk',
        'trial' => 'Trial',
        'product' => 'Produk',
        'product_type' => 'Product Type',
        'status' => 'Status',
    ],

    'reviews' => [
        'title' => 'Perlu Ditinjau',
        'description' => 'Trial yang perlu ditinjau oleh departemen Anda. Seluruh departemen yang terlibat dapat melakukan review melalui halaman ini.',
        'round' => 'Putaran',
        'reviewer' => 'Peninjau',
        'comment' => 'Komentar',
        'action' => 'Tinjau & Review',
        'empty' => 'Tidak ada review yang tertunda.',
        'item_label' => 'review',
        'status' => [
            'pending' => 'Pending',
            'reviewed' => 'Reviewed',
        ],
    ],

    'approvals' => [
        'title' => 'Perlu Disetujui',
        'description' => 'Trial yang menunggu keputusan akhir dari Manager QAC / approver. Seluruh approver yang ditunjuk dapat memberikan keputusan melalui halaman ini.',
        'approver' => 'Approver',
        'action' => 'Tinjau & Putuskan',
        'empty' => 'Tidak ada trial yang menunggu approval.',
    ],
];
