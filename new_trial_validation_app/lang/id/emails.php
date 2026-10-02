<?php

// Notification emails (app/Mail/*, resources/views/emails/*). Rendered in
// the language of the user whose action triggered the email.
return [
    'common' => [
        'greeting' => 'Yth. :name,',
        'trial_code' => 'Kode Trial',
        'product' => 'Produk',
        'thanks' => 'Terima kasih,',
    ],

    'review_requested' => [
        'subject' => 'Trial :code Memerlukan Review Anda',
        'heading' => 'Trial Memerlukan Review Anda',
        'intro' => 'Sebuah trial telah diajukan untuk direview oleh departemen :department, dan Anda ditugaskan sebagai reviewer.',
        'button' => 'Buka & Review Trial',
        'outro' => 'Silakan klik tombol di atas untuk melihat detail trial dan mengirimkan review Anda.',
    ],

    'approval_requested' => [
        'subject' => 'Trial :code Menunggu Approval Anda',
        'heading' => 'Trial Menunggu Approval Anda',
        'intro' => 'Seluruh reviewer departemen telah menyelesaikan review untuk trial berikut, dan trial ini sekarang menunggu approval Anda.',
        'button' => 'Buka & Approve Trial',
        'outro' => 'Silakan klik tombol di atas untuk melihat detail trial dan mengirimkan keputusan approval Anda.',
    ],

    'line_config_signoff' => [
        'subject' => 'Line Configuration Report — Diperlukan Sign-Off :label (:code)',
        'heading' => 'Line Configuration Report: Sign-Off :label',
        'intro' => 'Anda ditunjuk sebagai :label pada :report untuk trial di bawah ini — formulir pengaturan lini produksi yang terlampir pada trial, bukan keputusan review/approval trial itu sendiri. Tidak ada tindakan yang diperlukan pada trial secara keseluruhan, hanya sign-off pada laporan ini.',
        'button' => 'Buka Line Configuration Report',
        'outro' => 'Silakan klik tombol di atas, gulir ke bagian :report, lalu gunakan tombol :label untuk mengonfirmasi — tanggal akan tercatat secara otomatis.',
    ],

    'line_config_returned' => [
        'subject' => 'Line Configuration Report Dikembalikan untuk Revisi (:code)',
        'heading' => 'Line Configuration Report Dikembalikan untuk Revisi',
        'intro' => ':report yang Anda isi untuk trial di bawah ini telah dikembalikan untuk revisi — hal ini hanya berkaitan dengan laporan tersebut, bukan keputusan review/approval trial itu sendiri.',
        'intro_by' => ':report yang Anda isi untuk trial di bawah ini telah dikembalikan untuk revisi oleh :stage — hal ini hanya berkaitan dengan laporan tersebut, bukan keputusan review/approval trial itu sendiri.',
        'reason' => 'Alasan pengembalian:',
        'button' => 'Buka Line Configuration Report',
        'outro' => 'Silakan perbaiki laporan tersebut dan ajukan kembali untuk sign-off.',
    ],
];
