<?php

// Login / password-reset screens, the guest layout, and the account Settings
// pages (profile, security, appearance).
return [
    'auth_layout' => [
        'back_to_portal' => 'Kembali ke Portal',
        'tagline' => 'Pengelolaan trial produksi dari pengajuan, review lintas departemen, hingga approval dan pelaporan — dalam satu alur kerja.',
        'highlights' => [
            'form' => 'Form trial, validasi parameter & penimbangan',
            'review' => 'Review berjenjang per departemen',
            'approval' => 'Approval, laporan, dan riwayat aktivitas',
        ],
        'copyright' => '© :year Cosmax Indonesia. Hak cipta dilindungi undang-undang.',
    ],

    'login' => [
        'head' => 'Masuk',
        'title' => 'Masuk ke akun Anda',
        'description' => 'Masukkan email dan password Anda di bawah ini untuk masuk',
        'email' => 'Alamat email',
        'password' => 'Password',
        'forgot' => 'Lupa password?',
        'remember' => 'Ingat saya',
        'submit' => 'Masuk',
    ],

    'forgot_password' => [
        'head' => 'Lupa password',
        'title' => 'Lupa password',
        'description' => 'Masukkan email Anda untuk menerima tautan pengaturan ulang password',
        'email' => 'Alamat email',
        'submit' => 'Kirim tautan pengaturan ulang password',
        'return_to' => 'Atau, kembali ke halaman',
        'log_in' => 'masuk',
    ],

    'reset_password' => [
        'head' => 'Atur ulang password',
        'title' => 'Atur ulang password',
        'description' => 'Silakan masukkan password baru Anda di bawah ini',
        'email' => 'Email',
        'password' => 'Password',
        'confirm' => 'Konfirmasi password',
        'submit' => 'Atur ulang password',
    ],

    'password_input' => [
        'show' => 'Tampilkan password',
        'hide' => 'Sembunyikan password',
    ],

    'appearance_tabs' => [
        'light' => 'Terang',
        'dark' => 'Gelap',
        'system' => 'Sistem',
    ],

    'settings' => [
        'title' => 'Pengaturan',
        'description' => 'Kelola profil dan pengaturan akun Anda',
        'nav' => [
            'profile' => 'Profil',
            'security' => 'Keamanan',
            'appearance' => 'Tampilan',
        ],
        'save' => 'Simpan',
    ],

    'profile' => [
        'head' => 'Pengaturan profil',
        'title' => 'Profil',
        'description' => 'Perbarui nama dan alamat email Anda',
        'name' => 'Nama',
        'name_placeholder' => 'Nama lengkap',
        'email' => 'Alamat email',
    ],

    'security' => [
        'head' => 'Pengaturan keamanan',
        'title' => 'Ubah password',
        'description' => 'Pastikan akun Anda menggunakan password yang panjang dan acak agar tetap aman',
        'current_password' => 'Password saat ini',
        'new_password' => 'Password baru',
        'confirm_password' => 'Konfirmasi password',
    ],

    'appearance' => [
        'head' => 'Pengaturan tampilan',
        'title' => 'Pengaturan tampilan',
        'description' => 'Perbarui pengaturan tampilan akun Anda',
    ],

    'delete_user' => [
        'title' => 'Hapus akun',
        'description' => 'Hapus akun Anda beserta seluruh datanya',
        'warning' => 'Peringatan',
        'warning_text' => 'Harap berhati-hati, tindakan ini tidak dapat dibatalkan.',
        'button' => 'Hapus akun',
        'confirm_title' => 'Apakah Anda yakin ingin menghapus akun Anda?',
        'confirm_description' => 'Setelah akun Anda dihapus, seluruh data di dalamnya juga akan terhapus secara permanen. Silakan masukkan password Anda untuk mengonfirmasi bahwa Anda ingin menghapus akun secara permanen.',
        'password' => 'Password',
    ],

    'header' => [
        'navigation_menu' => 'Menu navigasi',
        'search' => 'Cari',
        'repository' => 'Repositori',
        'documentation' => 'Dokumentasi',
    ],

    'alert_error' => [
        'title' => 'Terjadi kesalahan.',
    ],

    'toast' => [
        'profile_updated' => 'Profil berhasil diperbarui.',
        'password_updated' => 'Password berhasil diperbarui.',
    ],
];
