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

    public const INTENT_SUMMARY = 'summary';

    public const INTENT_MY_TRIALS = 'my_trials';

    public const INTENT_LATEST_TRIALS = 'latest_trials';

    public const INTENT_STEP_DURATION = 'step_duration';

    public const INTENT_HOW_TO = 'how_to';

    public const INTENT_SEARCH_TRIALS = 'search_trials';

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

    private const SUMMARY_WORDS = [
        'ringkasan', 'rekap', 'gambaran', 'statistik', 'secara keseluruhan', 'total trial', 'jumlah total', 'summary', 'overview', 'statistics', 'overall', 'in total',
        '요약', '통계', '현황', '전체 트라이얼',
    ];

    private const LATEST_WORDS = [
        'terbaru', 'terakhir dibuat', 'baru dibuat', 'baru-baru', 'latest', 'newest', 'recent', 'recently',
        '최신', '최근',
    ];

    private const TRIAL_WORDS = ['trial', '트라이얼'];

    /** Standalone words that mean "mine"; Korean has no word boundaries so those are plain substrings. */
    private const OWN_PATTERN = '/(?<![\p{L}\p{N}])(?:saya|aku|milikku|my|mine|i created|i made)(?![\p{L}\p{N}])|내 |내가|제 |나의/u';

    private const SEARCH_VERBS_PATTERN = '/(?<![\p{L}\p{N}])(?:cari(?:kan)?|search(?: for)?|find)(?![\p{L}\p{N}])\s*(.*)$/u';

    private const SEARCH_KOREAN_PATTERN = '/^(.+?)\s*(?:을|를)?\s*(?:찾아|검색)/u';

    /** Filler tokens trimmed from both ends of a search keyword. */
    private const SEARCH_FILLER = [
        'trial', 'trials', 'dengan', 'untuk', 'yang', 'nama', 'bernama', 'kode', 'produk', 'product', 'for', 'with', 'named', 'the', 'a', 'me',
        'dong', 'ya', 'please', 'pls', 'tolong', '트라이얼', '제품',
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

        // "my trials ..." needs an own-word AND a trial-word, so "tugas saya" (my_queue) stays separate.
        if (($this->containsAny($text, self::TRIAL_WORDS) && preg_match(self::OWN_PATTERN, $text)) || $this->containsAny($text, $extra(self::INTENT_MY_TRIALS))) {
            return $this->result(self::INTENT_MY_TRIALS, ['status' => $status, 'product_type' => $productType]);
        }

        if ($this->containsAny($text, [...self::LATEST_WORDS, ...$extra(self::INTENT_LATEST_TRIALS)])) {
            $params = ['status' => $status, 'product_type' => $productType];
            if (preg_match('/(?<![\d-])(\d{1,2})(?!\d)/', $text, $m)) {
                $params['limit'] = min(10, max(1, (int) $m[1]));
            }

            return $this->result(self::INTENT_LATEST_TRIALS, $params);
        }

        if ($status === null && $this->containsAny($text, [...self::SUMMARY_WORDS, ...$extra(self::INTENT_SUMMARY)])) {
            return $this->result(self::INTENT_SUMMARY);
        }

        $keyword = $this->searchKeyword($text);
        if ($keyword !== null && $status === null) {
            return $this->result(self::INTENT_SEARCH_TRIALS, ['keyword' => $keyword]);
        }

        if ($status !== null || $this->containsAny($text, [...self::LIST_WORDS, ...$extra(self::INTENT_TRIALS_BY_STATUS)])) {
            return $this->result(self::INTENT_TRIALS_BY_STATUS, [
                'status' => $status,
                'product_type' => $productType,
            ]);
        }

        return $this->result(self::INTENT_UNKNOWN);
    }

    /**
     * Built-in keyword lists per intent, so a suggester can compare an
     * unanswered question against what each intent already understands.
     *
     * @return array<string, array<int, string>>
     */
    public function keywords(): array
    {
        return [
            self::INTENT_HELP => self::HELP_WORDS,
            self::INTENT_MY_QUEUE => self::MY_QUEUE_WORDS,
            self::INTENT_STUCK_TRIALS => self::STUCK_WORDS,
            self::INTENT_NOT_OK_HOTSPOTS => self::NOT_OK_WORDS,
            self::INTENT_WEIGHING_NORM => self::WEIGHING_WORDS,
            self::INTENT_TRIALS_BY_STATUS => [...self::LIST_WORDS, ...array_merge(...array_values(self::STATUS_ALIASES))],
            self::INTENT_SUMMARY => self::SUMMARY_WORDS,
            self::INTENT_LATEST_TRIALS => self::LATEST_WORDS,
            self::INTENT_SEARCH_TRIALS => ['cari', 'search', 'find', '찾아', '검색'],
        ];
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
            self::INTENT_SUMMARY,
            self::INTENT_MY_TRIALS,
            self::INTENT_LATEST_TRIALS,
            self::INTENT_SEARCH_TRIALS,
        ];
    }

    /** The text to look for after "cari / search / find" (or before Korean 찾아), or null when there is none. */
    private function searchKeyword(string $text): ?string
    {
        $raw = null;
        if (preg_match(self::SEARCH_VERBS_PATTERN, $text, $m)) {
            $raw = $m[1];
        } elseif (preg_match(self::SEARCH_KOREAN_PATTERN, $text, $m)) {
            $raw = $m[1];
        }

        if ($raw === null) {
            return null;
        }

        $tokens = preg_split('/\s+/u', trim($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        while ($tokens !== [] && in_array($tokens[0], self::SEARCH_FILLER, true)) {
            array_shift($tokens);
        }
        while ($tokens !== [] && in_array(end($tokens), self::SEARCH_FILLER, true)) {
            array_pop($tokens);
        }

        $keyword = trim(implode(' ', $tokens), " \t\n\r\"'?.!");

        return mb_strlen($keyword) >= 2 ? mb_substr($keyword, 0, 60) : null;
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
