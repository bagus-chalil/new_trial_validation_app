<?php

// Rule-based trial bot: answer templates (App\Services\Ai\Bot\BotAnswerComposer).
// Placeholders use :name. Every key must exist in id/en/ko.
return [
    'title' => 'Asisten Trial',

    'answers' => [
        'my_queue' => [
            'intro' => 'Ringkasan pekerjaan Anda saat ini:',
            'draft' => ':count trial Draft yang perlu Anda lanjutkan.',
            'need_revision' => ':count trial Need Revision yang perlu Anda perbaiki.',
            'in_progress' => ':count trial Anda sedang berjalan (In Review / Ready for Approval), tidak ada aksi dari Anda.',
            'pending_reviews' => ':count review menunggu Anda.',
            'pending_approvals' => ':count trial menunggu approval Anda.',
            'empty' => 'Tidak ada pekerjaan yang menunggu Anda saat ini.',
        ],

        'trials_by_status' => [
            'total' => 'Ada :total trial:filter.',
            'none' => 'Tidak ada trial yang cocok:filter.',
            'latest' => 'Terbaru:',
            'filter_status' => ' dengan status :status',
            'filter_type' => ' untuk jenis produk :type',
            'more' => '(menampilkan :shown teratas)',
        ],

        'trial_status' => [
            'found' => 'Trial :code (:product): status :status, tahap :step.',
            'pending_with' => 'Saat ini menunggu :who.',
            'revision' => 'Revisi ke-:no.',
            'not_found' => 'Trial :code tidak ditemukan atau Anda tidak memiliki akses.',
        ],

        'stuck_trials' => [
            'total' => 'Ada :total trial yang tidak bergerak lebih dari :days hari.',
            'none' => 'Tidak ada trial yang tertahan lebih dari :days hari.',
            'item' => ':code (:product) - :status, menunggu :who, terakhir diperbarui :date',
            'nobody' => 'belum ada penerima',
        ],

        'not_ok_hotspots' => [
            'intro' => 'Parameter yang paling sering NOT OK:',
            'item' => ':type / :parameter: :count dari :total trial (:rate%)',
            'none' => 'Belum ada pola NOT OK yang cukup kuat untuk dilaporkan (butuh minimal 5 trial per kelompok).',
            'scope' => 'Berdasarkan pembelajaran otomatis dari data trial lampau.',
        ],

        'weighing_norm' => [
            'found' => 'Untuk :type bagian :section, sebaran normal antar sampel timbangan (koefisien variasi) sekitar :median%. Di atas :high% dianggap tidak wajar. Berdasarkan :n trial, keyakinan :confidence.',
            'none' => 'Belum ada norma timbangan yang cukup andal untuk :type bagian :section (butuh minimal 5 trial yang benar-benar menimbang).',
            'need_type' => 'Sebutkan jenis produk dan bagiannya, contoh: "sebaran timbangan Tube Packaging".',
        ],

        'summary' => [
            'intro' => 'Ringkasan trial yang bisa Anda lihat: total :total (:filling filling, :mixing mixing).',
            'none' => 'Belum ada trial yang bisa Anda lihat.',
        ],

        'my_trials' => [
            'total' => 'Anda membuat :total trial:filter.',
            'none' => 'Anda belum membuat trial:filter.',
        ],

        'latest_trials' => [
            'intro' => ':count trial terbaru:filter:',
            'none' => 'Tidak ada trial yang cocok:filter.',
        ],

        'search_trials' => [
            'total' => ':total trial cocok dengan ":keyword":',
            'none' => 'Tidak ada trial yang cocok dengan ":keyword" (atau Anda tidak punya akses).',
        ],

        'trial_not_ok' => [
            'intro' => 'Trial :code punya :count parameter NOT OK dari :total yang diperiksa:',
            'item' => ':parameter - hasil: :result; catatan: :remark',
            'none' => 'Trial :code tidak punya parameter NOT OK.',
            'not_validated' => 'Trial :code belum memiliki hasil validasi.',
        ],

        'trial_reviews' => [
            'intro' => 'Status review trial :code (putaran :round):',
            'done' => ':dept: sudah direview oleh :who pada :date',
            'pending' => ':dept: belum direview, menunggu :who',
            'none' => 'Trial :code belum diajukan untuk review.',
        ],

        'step_duration' => [
            'intro' => 'Lama proses tiap tahap (dipelajari dari trial lampau, biasanya / lambat):',
            'item' => ':stage:scope: biasanya :typical, dianggap lambat di atas :slow (:n trial)',
            'stages' => [
                'prepare' => 'Persiapan trial (dibuat sampai diajukan)',
                'review' => 'Review department',
                'approval' => 'Approval akhir',
            ],
            'hours' => ':n jam',
            'days' => ':n hari',
            'none' => 'Belum ada data durasi yang cukup andal (butuh minimal 5 trial per tahap).',
            'scope' => 'Berdasarkan pembelajaran otomatis dari data trial lampau.',
        ],

        'how_to' => [
            'choose' => 'Cara apa yang ingin Anda ketahui? Coba salah satu contoh ini:',
            'topics' => [
                'create_trial' => "Cara membuat trial baru:\n1. Buka menu Trials > New Trial.\n2. Isi data header (produk, scope, mesin, batch, dll.) lalu klik Simpan & Lanjutkan.\n3. Lanjutkan berurutan: Validation, Weighing Packaging, Weighing Filling, Attachments, lalu Review & Submit.",
                'validation' => "Cara mengisi Validation:\n1. Pilih keputusan tiap parameter: OK, NOT OK, atau N/A.\n2. Untuk NOT OK, hasil dan catatan wajib diisi.\n3. Semua parameter harus terisi, lalu Simpan.",
                'weighing' => "Cara mengisi Weighing (Packaging lalu Filling):\n1. Isi berat tiap sampel (angka, tidak negatif). Gunakan Add Sample bila perlu lebih banyak.\n2. Bila bagian ini tidak ditimbang, centang Skip.\n3. Simpan untuk lanjut ke bagian berikutnya.",
                'attachments' => "Cara melampirkan foto:\n1. Buka langkah Attachments, pilih kategori.\n2. Pilih foto (JPG/PNG/WEBP/GIF, maks. 10 MB per file) lalu Upload.\n3. Foto bisa dihapus selama trial masih bisa diedit.",
                'submit_review' => "Cara mengajukan review:\n1. Pastikan Validation sudah lengkap, lalu buka langkah Review & Submit.\n2. Centang department reviewer dan pilih orang yang mereview, lalu pilih approver.\n3. Klik Submit for Review.",
                'do_review' => "Cara melakukan review:\n1. Buka menu Need Review dan klik trial yang menunggu Anda.\n2. Pelajari data di halaman Report Summary.\n3. Tulis komentar review di bagian review lalu kirim.",
                'approve' => "Cara memberi approval:\n1. Buka menu Need Approval dan klik trial yang ditugaskan kepada Anda.\n2. Setelah membaca Report Summary, pilih Approve, Need Revision, atau Reject.\n3. Isi komentar dan password akun Anda sebagai tanda tangan elektronik.",
                'revision' => "Cara menangani Need Revision:\n1. Buka menu Need Revision dan klik trial Anda.\n2. Perbaiki data sesuai komentar approver.\n3. Ajukan lagi lewat Review & Submit.",
            ],
        ],

        'help' => [
            'text' => 'Saya asisten trial. Saya bisa menjawab hal-hal berikut, coba salah satu contoh ini:',
        ],

        'unknown' => [
            'text' => 'Maaf, saya belum mengerti pertanyaan itu. Pertanyaan Anda sudah dicatat agar admin bisa mengajarkannya ke saya. Sementara itu, coba salah satu contoh berikut:',
        ],

        'unknown_did_you_mean' => [
            'text' => 'Maaf, saya belum yakin maksud pertanyaan itu. Pertanyaan Anda sudah dicatat agar admin bisa mengajarkannya ke saya. Apakah maksud Anda salah satu ini?',
        ],
    ],

    'widget' => [
        'placeholder' => 'Tanya soal trial…',
        'send' => 'Kirim',
        'close' => 'Tutup',
        'open' => 'Buka asisten',
        'helpful' => 'Membantu',
        'not_helpful' => 'Kurang membantu',
        'thanks' => 'Terima kasih atas masukannya.',
        'error' => 'Terjadi kesalahan. Coba lagi.',
        'try_asking' => 'Coba tanyakan:',
        'greeting' => 'Halo! Tanyakan tugas Anda, status trial, atau trial yang tertahan.',
        'open_trial' => 'Buka trial',
    ],

    'confidence' => [
        'rendah' => 'rendah',
        'sedang' => 'sedang',
        'tinggi' => 'tinggi',
    ],

    'examples' => [
        'my_queue' => 'Apa tugas saya hari ini?',
        'trials_by_status' => 'Berapa trial yang sedang In Review?',
        'trial_status' => 'Status TRIAL-20260914-124439-QF1D',
        'stuck_trials' => 'Trial mana yang macet lebih dari 14 hari?',
        'not_ok_hotspots' => 'Parameter apa yang paling sering NOT OK?',
        'weighing_norm' => 'Sebaran timbangan Tube Packaging normalnya berapa?',
        'summary' => 'Berikan ringkasan semua trial',
        'my_trials' => 'Tampilkan trial saya yang masih Draft',
        'latest_trials' => 'Tampilkan 5 trial terbaru',
        'search_trials' => 'Cari trial Tube',
        'trial_not_ok' => 'Kenapa TRIAL-20260914-124439-QF1D NOT OK?',
        'trial_reviews' => 'Siapa yang belum review TRIAL-20260914-124439-QF1D?',
        'step_duration' => 'Berapa lama proses review biasanya?',
        'how_to' => 'Bagaimana cara membuat trial baru?',
    ],

    'how_to_examples' => [
        'create_trial' => 'Bagaimana cara membuat trial baru?',
        'validation' => 'Cara mengisi validasi?',
        'weighing' => 'Cara mengisi timbangan?',
        'attachments' => 'Cara upload foto lampiran?',
        'submit_review' => 'Cara submit review?',
        'do_review' => 'Cara melakukan review?',
        'approve' => 'Cara memberi approval?',
        'revision' => 'Cara menangani revisi?',
    ],
];
