<?php

// Server-side messages: toast notifications after saving, and the custom
// validation / completeness messages built in FormRequests and Actions.
return [
    'toast' => [
        'trial_created' => 'Trial berhasil dibuat.',
        'trial_updated' => 'Trial berhasil diperbarui.',
        'trial_deleted' => 'Trial :code dipindahkan ke Tempat Sampah.',
        'validation_saved' => 'Validasi berhasil disimpan.',
        'weighing_saved' => 'Penimbangan berhasil disimpan.',
        'photos_uploaded' => ':count foto berhasil diunggah.',
        'upload_partial' => 'Sebagian file gagal: :errors',
        'attachment_deleted' => 'Lampiran berhasil dihapus.',
        'submitted_for_review' => 'Trial berhasil diajukan untuk review.',
        'review_saved' => 'Review berhasil disimpan.',
        'approval_saved' => 'Keputusan approval berhasil disimpan.',
        'additional_uploaded' => ':count lampiran tambahan berhasil diunggah.',
        'additional_none_uploaded' => 'Tidak ada file yang berhasil diunggah.',
        'additional_deleted' => 'Lampiran tambahan berhasil dihapus.',
        'line_config_saved' => 'Line Configuration Report berhasil disimpan.',
        'line_config_draft_saved' => 'Draft Line Configuration Report berhasil disimpan.',
        'line_config_submitted' => 'Line Configuration Report berhasil dikirim untuk approval.',
        'line_config_confirmed' => ':label berhasil dikonfirmasi.',
        'line_config_returned' => 'Line Configuration Report dikembalikan untuk revisi.',
    ],

    'upload' => [
        'failed' => 'Unggah file ke-:number gagal.',
        'too_large' => 'File :name melebihi 10 MB.',
        'not_image' => 'File :name bukan gambar yang diizinkan.',
        'store_failed' => 'File :name gagal disimpan.',
    ],

    'completeness' => [
        'required' => ':field wajib diisi.',
        'no_parameters' => 'Parameter validasi untuk product type :type belum dikonfigurasi.',
        'no_decision' => 'Parameter :name belum memiliki decision.',
        'not_ok_incomplete' => 'Parameter :name NOT OK wajib punya result dan remark.',
    ],

    'validation' => [
        'parameter_missing' => 'Parameter :name belum terisi.',
        'parameter_not_ok' => 'Parameter :name NOT OK wajib isi Result dan Remark.',
        'weighing_invalid' => 'Sampel penimbangan harus berupa angka dan tidak boleh negatif.',
        'weighing_empty' => 'Masukkan minimal 1 sampel penimbangan atau centang Lewati.',
        'submit_incomplete' => 'Belum bisa mengajukan review: :errors',
        'reviewer_required' => 'Pilih reviewer untuk departemen :department.',
        'reviewer_invalid' => 'Reviewer yang dipilih tidak valid untuk departemen :department.',
        'not_ready_for_approval' => 'Trial belum siap untuk approval.',
        'signature_wrong' => 'Password e-signature salah.',
        'return_reason_min_words' => 'Alasan Return minimal 5 kata.',
        'additional_required' => 'Pilih minimal satu file.',
        'additional_max_per_upload' => 'Maksimal :max file per unggahan.',
        'additional_too_large' => 'Ukuran file maksimal 10 MB.',
        'additional_file_type' => 'Hanya file PDF atau gambar (JPG, PNG, WEBP, GIF) yang diizinkan.',
        'additional_remaining' => 'Maksimal :max lampiran per trial — sisa slot :remaining.',
        'additional_limit_reached' => 'Batas :max lampiran tambahan untuk trial ini sudah tercapai.',
    ],
];
