<?php

// Trial wizard pages (Header form, Validation, Weighing, Attachments, Review
// & Submit). Field labels shared with the Report page come from
// report.info / report.header / report.validation / report.weighing.
return [
    'save_next' => 'Simpan & Lanjutkan',
    'progress_note' => 'Progres trial ini sudah sampai tahap :step.',

    'form' => [
        'title_new' => 'Trial Baru',
        'title_edit' => 'Ubah Trial — :code',
        'description' => 'Lengkapi informasi header trial. Langkah validasi, penimbangan, review, dan approval menyusul di layar terpisah.',
        'sections' => [
            'product' => 'Informasi Produk',
            'scope' => 'Cakupan & Mesin',
            'batch' => 'Batch & Tim',
            'reason' => 'Alasan & BOM',
        ],
        'product' => 'Produk',
        'placeholders' => [
            'product' => 'Pilih produk',
            'product_search' => 'Cari produk...',
            'product_type' => 'Pilih tipe produk',
            'validation_category' => 'Pilih kategori',
            'risk_level' => 'Pilih tingkat risiko',
            'validation_scope' => 'Pilih cakupan',
            'validation_scope_search' => 'Cari cakupan...',
            'machine_used' => 'Pilih mesin',
            'machine_used_search' => 'Cari mesin...',
        ],
        'save_changes' => 'Simpan Perubahan',
    ],

    'validation' => [
        'page_title' => 'Validasi — :code',
        'title' => 'Validasi Parameter Trial — :type',
        'description' => 'Isi Keputusan, Hasil, dan Catatan untuk setiap parameter validasi.',
        'no_parameters' => 'Parameter validasi untuk product type ini belum dikonfigurasi.',
    ],

    'weighing' => [
        'page_title' => 'Penimbangan :label — :code',
        'title' => 'Penimbangan :label',
        'description' => 'Masukkan hasil sampling untuk trial :code.',
        'labels' => [
            'packaging' => 'Kemasan Kosong (gr)',
            'filling' => 'Berat Filling (gr)',
        ],
        'skip' => 'Lewati :label (N/A)',
        'add_sample' => 'Tambah Sampel',
        'remove_sample' => 'Hapus sampel :number',
    ],

    'attachments' => [
        'page_title' => 'Lampiran — :code',
        'title' => 'Lampiran',
        'description' => 'Unggah dan kelola foto bukti trial :code.',
        'upload_title' => 'Unggah Foto',
        'category' => 'Kategori',
        'category_placeholder' => 'Pilih kategori...',
        'category_search' => 'Cari kategori...',
        'caption' => 'Keterangan (Opsional)',
        'caption_placeholder' => 'Tambahkan keterangan singkat tentang foto ini...',
        'photos' => 'Foto',
        'upload' => 'Unggah',
        'remove' => 'Batalkan',
        'delete' => 'Hapus',
        'delete_confirm' => 'Hapus foto ini?',
        'readonly' => 'Lampiran hanya dapat dibaca. Foto hanya bisa dihapus saat status Draft atau Need Revision.',
        'photo_count' => ':count foto',
        'empty' => 'Belum ada lampiran.',
        'continue_review' => 'Lanjut ke Peninjauan',
    ],

    'review' => [
        'page_title' => 'Peninjauan — :code',
        'title' => 'Tinjau & Kirim',
        'description' => 'Kirim trial :code untuk direview departemen terkait.',
        'status_title' => 'Status Review Departemen',
        'assigned_to' => 'Ditugaskan Kepada',
        'not_ready' => 'Belum siap diajukan untuk review',
        'select_title' => 'Pilih Departemen / Tim Review',
        'no_reviewer' => 'Belum ada pengguna dengan tim review :department. Atur di Hak Akses.',
        'reviewer_placeholder' => 'Pilih reviewer...',
        'reviewer_search' => 'Cari reviewer...',
        'approver' => 'Approver',
        'approver_placeholder' => 'Pilih approver...',
        'approver_search' => 'Cari approver...',
        'view_detail' => 'Lihat Detail Trial',
        'submit' => 'Ajukan untuk Review',
    ],
];
