<?php

// "Ringkasan trial" for approvers and reviewers on the per-trial Report Summary. The `facts.*`
// sentences are BOTH shown on the page and sent (as facts) to the Vision Service, so keep them
// plain, one statement each, and free of names.
return [
    'title' => 'Ringkasan trial',
    'subtitle' => 'Dirangkum dari data trial yang tercatat. Hanya membantu membaca, keputusan tetap di tangan Anda.',
    'button' => 'Tulis ringkasan AI',
    'button_busy' => 'Menulis...',
    'wait_hint' => 'Bisa sampai 1-2 menit (server AI memproses satu per satu).',
    'ai_label' => 'Ringkasan AI (saran)',
    'finding' => 'Temuan',

    'errors' => [
        'unreachable' => 'Vision Service tidak terjangkau dari server ini.',
        'outdated' => 'Vision Service di server belum diperbarui. Pakai poin ringkasan di bawah.',
        'rejected' => 'Ringkasan AI ditolak Vision Service (melanggar aturan advisory). Pakai poin ringkasan di bawah.',
        'failed' => 'Ringkasan AI gagal (Ollama/Vision Service tidak memberi jawaban).',
        'busy' => 'Ringkasan sebelumnya masih berjalan, tunggu sebentar.',
        'network' => 'Tidak bisa menghubungi server.',
    ],

    'facts' => [
        'identity' => 'Trial :code, produk :product (tipe :type), kategori validasi :category, risiko :risk.',
        'identity_anonymous' => 'Trial :code, tipe produk :type, kategori validasi :category, risiko :risk.',
        'status' => 'Status :status, langkah :step, putaran review ke-:round.',
        'revised' => 'Trial ini sudah direvisi :count kali.',
        'decision_note' => 'Catatan keputusan approver: :note',
        'validation_none' => 'Belum ada hasil validasi.',
        'validation_counts' => 'Validasi: :ok dari :total parameter OK, :not_ok NOT OK, :na N/A.',
        'validation_missing' => ':count parameter validasi belum diisi.',
        'not_ok' => 'NOT OK: :parameter, hasil ":result", catatan ":remark".',
        'not_ok_more' => 'Masih ada :count parameter NOT OK lainnya.',
        'weighing_none' => 'Weighing :section belum diisi.',
        'weighing_skipped' => 'Weighing :section tidak dilakukan (skip).',
        'weighing_data' => 'Weighing :section: :count sampel, rata-rata :avg, min :min, maks :max.',
        'photos' => 'Foto lampiran: :count.',
        'photos_none' => 'Belum ada foto lampiran.',
        'review_pending' => 'Review menunggu departemen: :departments.',
        'review_ok' => 'Review OK tanpa catatan tambahan dari: :departments.',
        'review_note' => 'Catatan review :department: :note',
        'review_more' => 'Masih ada :count catatan review lainnya.',
    ],
];
