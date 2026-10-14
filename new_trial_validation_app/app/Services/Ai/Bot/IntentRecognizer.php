<?php

namespace App\Services\Ai\Bot;

/**
 * Rule-based question recognizer for the trial bot: keywords and aliases in
 * Indonesian / English / Korean, no model, no network. Pure — it only turns
 * text into an intent + params; the TrialBotTools fetch the data and the
 * answer templates build the sentence. Product types are injected (they live
 * in master_options) so this class stays testable without a database.
 *
 * Anything it cannot place returns INTENT_UNKNOWN, which the caller logs as
 * an unmatched question (the intent backlog) and answers with suggestions.
 */
class IntentRecognizer
{
    public const INTENT_MY_QUEUE = 'my_queue';

    public const INTENT_TRIALS_BY_STATUS = 'trials_by_status';

    public const INTENT_TRIAL_STATUS = 'trial_status';

    public const INTENT_STUCK_TRIALS = 'stuck_trials';

    public const INTENT_NOT_OK_HOTSPOTS = 'not_ok_hotspots';

    public const INTENT_WEIGHING_NORM = 'weighing_norm';

    public const INTENT_HELP = 'help';

    public const INTENT_UNKNOWN = 'unknown';

    private const HELP_WORDS = [
        'kamu bisa apa', 'bisa apa', 'bantuan', 'bantu saya', 'apa saja yang bisa', 'help', 'what can you do', 'what do you do',
        '도움말', '뭘 할 수', '무엇을 할 수',
    ];

    /** Longest / most specific aliases first: "need revision" before "revision", "ready for approval" before "approved". */
    private const STATUS_ALIASES = [
        'Ready for Approval' => ['ready for approval', 'siap approval', 'siap diapprove', 'menunggu approval', 'waiting for approval', '승인 대기', '승인대기'],
        'Need Revision' => ['need revision', 'perlu revisi', 'butuh revisi', 'revisi', 'revision', '수정 필요', '수정필요'],
        'In Review' => ['in review', 'sedang direview', 'sedang review', 'dalam review', '검토 중', '검토중'],
        'Approved' => ['approved', 'disetujui', '승인됨', '승인 완료'],
        'Rejected' => ['rejected', 'ditolak', '반려', '거절'],
        'Draft' => ['draft', 'draf', '초안'],
    ];

    private const MY_QUEUE_WORDS = [
        'tugas saya', 'tugasku', 'pekerjaan saya', 'antrian saya', 'antrean saya', 'yang harus saya', 'apa yang harus', 'harus saya kerjakan', 'pending saya',
        'my queue', 'my tasks', 'my work', 'todo', 'what should i do', 'waiting for me',
        '내 작업', '내 할 일', '할 일', '내 대기',
    ];

    private const STUCK_WORDS = [
        'stuck', 'macet', 'tertahan', 'lama tidak', 'tidak bergerak', 'terlalu lama', 'mandek', 'overdue', 'lama menunggu', 'long time',
        '지연', '오래', '정체',
    ];

    private const NOT_OK_WORDS = [
        'not ok', 'notok', 'sering gagal', 'paling sering gagal', 'sering tidak ok', 'hotspot', 'fail the most', 'fails most', 'most failing', 'often fail',
        '부적합', '자주 실패', '불합격',
    ];

    private const WEIGHING_WORDS = [
        'timbang', 'berat', 'weighing', 'weight', 'spread', 'sebaran',
        '칭량', '무게', '중량',
    ];

    private const SECTION_ALIASES = [
        'Packaging' => ['packaging', 'kemasan', '포장'],
        'Filling' => ['filling', 'pengisian', '충전'],
    ];

    private const LIST_WORDS = [
        'berapa', 'jumlah', 'daftar', 'list', 'tampilkan', 'lihat', 'show', 'how many', 'count', 'trial apa', 'which trial',
        '몇', '목록', '보여',
    ];

    /**
     * @param  array<int, string>  $productTypes  known product_type names (master_options)
     * @param  array{phrases?: array<string, array<int, string>>, status?: array<string, array<int, string>>}  $custom  phrases taught from the admin GUI (AiBotAlias::grouped())
     * @return array{intent:string, params:array<string, mixed>}
     */
    public function recognize(string $question, array $productTypes = [], array $custom = []): array
    {
        $text = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $question) ?? ''));

        if ($text === '') {
            return $this->result(self::INTENT_UNKNOWN);
        }

        // A concrete trial code beats everything else.
        if (preg_match('/TRIAL-\d{8}-\d{6}(?:-[A-Z0-9]+)?/i', $question, $m)) {
            return $this->result(self::INTENT_TRIAL_STATUS, ['trial_code' => strtoupper($m[0])]);
        }

        $productType = $this->matchProductType($text, $productTypes);
        $extra = fn (string $intent): array => $custom['phrases'][$intent] ?? [];

        if ($this->containsAny($text, [...self::HELP_WORDS, ...$extra(self::INTENT_HELP)])) {
            return $this->result(self::INTENT_HELP);
        }

        if ($this->containsAny($text, [...self::MY_QUEUE_WORDS, ...$extra(self::INTENT_MY_QUEUE)])) {
            return $this->result(self::INTENT_MY_QUEUE);
        }

        if ($this->containsAny($text, [...self::STUCK_WORDS, ...$extra(self::INTENT_STUCK_TRIALS)])) {
            $params = [];
            if (preg_match('/(\d{1,3})\s*(?:hari|days?|d\b|일)/u', $text, $m)) {
                $params['days'] = max(1, (int) $m[1]);
            }

            return $this->result(self::INTENT_STUCK_TRIALS, $params);
        }

        if ($this->containsAny($text, [...self::NOT_OK_WORDS, ...$extra(self::INTENT_NOT_OK_HOTSPOTS)])) {
            return $this->result(self::INTENT_NOT_OK_HOTSPOTS, ['product_type' => $productType]);
        }

        if ($this->containsAny($text, [...self::WEIGHING_WORDS, ...$extra(self::INTENT_WEIGHING_NORM)])) {
            return $this->result(self::INTENT_WEIGHING_NORM, [
                'product_type' => $productType,
                'section' => $this->matchAliases($text, self::SECTION_ALIASES),
            ]);
        }

        $statusAliases = [];
        foreach (self::STATUS_ALIASES as $canonical => $words) {
            $statusAliases[$canonical] = [...$words, ...($custom['status'][$canonical] ?? [])];
        }

        $status = $this->matchAliases($text, $statusAliases);
        if ($status !== null || $this->containsAny($text, [...self::LIST_WORDS, ...$extra(self::INTENT_TRIALS_BY_STATUS)])) {
            return $this->result(self::INTENT_TRIALS_BY_STATUS, [
                'status' => $status,
                'product_type' => $productType,
            ]);
        }

        return $this->result(self::INTENT_UNKNOWN);
    }

    /**
     * Intents worth suggesting when a question is not understood.
     *
     * @return array<int, string>
     */
    public function suggestableIntents(): array
    {
        return [
            self::INTENT_MY_QUEUE,
            self::INTENT_TRIALS_BY_STATUS,
            self::INTENT_TRIAL_STATUS,
            self::INTENT_STUCK_TRIALS,
            self::INTENT_NOT_OK_HOTSPOTS,
            self::INTENT_WEIGHING_NORM,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{intent:string, params:array<string, mixed>}
     */
    private function result(string $intent, array $params = []): array
    {
        return ['intent' => $intent, 'params' => $params];
    }

    /** @param array<int, string> $needles */
    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (mb_strpos($text, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, array<int, string>>  $aliases  canonical value => aliases, most specific first
     */
    private function matchAliases(string $text, array $aliases): ?string
    {
        foreach ($aliases as $canonical => $words) {
            if ($this->containsAny($text, $words)) {
                return $canonical;
            }
        }

        return null;
    }

    /** @param array<int, string> $productTypes */
    private function matchProductType(string $text, array $productTypes): ?string
    {
        // Longest name first so "Lip Tint" wins over a shorter "Lip".
        usort($productTypes, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        foreach ($productTypes as $type) {
            $needle = mb_strtolower(trim($type));
            if ($needle !== '' && preg_match('/(?<![\p{L}\p{N}])'.preg_quote($needle, '/').'(?![\p{L}\p{N}])/u', $text)) {
                return $type;
            }
        }

        return null;
    }
}
