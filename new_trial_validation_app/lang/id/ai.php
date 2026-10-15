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

        'help' => [
            'text' => 'Saya asisten trial. Saya bisa menjawab hal-hal berikut, coba salah satu contoh ini:',
        ],

        'unknown' => [
            'text' => 'Maaf, saya belum mengerti pertanyaan itu. Pertanyaan Anda sudah dicatat agar admin bisa mengajarkannya ke saya. Sementara itu, coba salah satu contoh berikut:',
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
    ],
];
