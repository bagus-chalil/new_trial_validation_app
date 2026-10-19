<?php

// Bottleneck monitor: read-only overview of every active trial for all users.
return [
    'title' => 'Ikhtisar Seluruh Trial',
    'description' => 'Seluruh trial yang masih berjalan, terlepas dari siapa pemiliknya, agar terlihat di tahap dan di pihak mana trial tertahan. Halaman ini hanya untuk dilihat.',
    'stage_cards' => [
        'count' => ':count trial',
        'avg' => 'Rata-rata :days hari tanpa aktivitas',
        'oldest' => 'Terlama :days hari',
        'stale' => ':count trial melewati :days hari',
    ],
    'holders' => [
        'title' => 'Trial paling banyak tertahan di',
        'subtitle' => 'Pihak yang saat ini ditunggu. Klik baris untuk menyaring daftar di bawah.',
        'name' => 'Menunggu',
        'kind' => 'Jenis',
        'count' => 'Jumlah Trial',
        'avg_days' => 'Rata-rata (hari)',
        'oldest_days' => 'Terlama (hari)',
        'empty' => 'Tidak ada trial yang sedang berjalan.',
        'kinds' => [
            'owner' => 'Pemilik trial',
            'team' => 'Departemen reviewer',
            'approver' => 'Approver',
        ],
    ],
    'filters' => [
        'search' => 'Pencarian',
        'status' => 'Status',
        'holder' => 'Menunggu',
    ],
    'search_placeholder' => 'Kode trial atau nama produk',
    'table' => [
        'trial_code' => 'Kode Trial',
        'product_name' => 'Nama Produk',
        'status' => 'Status',
        'waiting_for' => 'Menunggu',
        'owner' => 'Pemilik',
        'idle' => 'Tanpa Aktivitas',
        'created_at' => 'Tanggal Dibuat',
        'no_access' => 'Anda tidak memiliki akses ke detail trial ini.',
        'empty' => 'Tidak ada trial yang sedang berjalan.',
        'item_label' => 'trial',
    ],
    'days' => ':count hari',
    'legend' => 'Warna kolom Tanpa Aktivitas: kuning mulai :warn hari, merah mulai :critical hari sejak aktivitas terakhir pada trial.',
];
