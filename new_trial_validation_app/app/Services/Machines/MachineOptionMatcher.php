<?php

namespace App\Services\Machines;

use App\Models\Equipment;
use App\Models\MachineLine;
use Illuminate\Support\Collection;

/**
 * Suggests which line (and, for shared packing equipment, which machine) a
 * Trial `machine_used` option name refers to. Pure and deterministic: explicit
 * naming rules first (score 0.90-0.95), then keyword rules for the
 * non-dedicated packing pool (0.50-0.60), then token similarity against line
 * names (capped at 0.85). Only a score >= AUTO_THRESHOLD is treated as "auto";
 * everything else is a suggestion an admin must confirm.
 */
class MachineOptionMatcher
{
    public const AUTO_THRESHOLD = 0.95;

    public const MIN_SCORE = 0.60;

    private const STOPWORDS = ['fill', 'filling', 'machine', 'mesin', 'new', 'no', 'mixer', 'uhm', 'auto', 'line', 'homo'];

    private const SYNONYMS = [
        'heinschel' => 'henschel',
        'lipstik' => 'lipstick',
        'eyliner' => 'eyeliner',
        'mask sheet' => 'masksheet',
        'wirapack' => 'wirpack',
        'tunel' => 'tunnel',
    ];

    /** Keyword pattern => [equipment name needle inside the pool line, reason]. */
    private const POOL_RULES = [
        '/l-?\s?bar/i' => ['L-Bar', 'keyword L-Bar'],
        '/shrink\s+(steam|tunel|tunnel|hotair|overwrap)|overwrap/i' => ['Thermal - Shrink', 'keyword shrink'],
        '/ink\s?jet/i' => ['Inkjet', 'keyword inkjet'],
        '/laser/i' => ['Laser Coding UV', 'keyword laser'],
        '/vision/i' => ['Vision Inspection', 'keyword vision'],
        '/induction/i' => ['Induction', 'keyword induction'],
        '/cartoning/i' => ['Carton', 'keyword cartoning'],
        '/label/i' => ['Labelling', 'keyword label'],
    ];

    /** @var Collection<int, MachineLine> */
    private Collection $lines;

    /** @var Collection<int, Equipment> */
    private Collection $equipment;

    /**
     * @param  Collection<int, MachineLine>  $lines
     * @param  Collection<int, Equipment>  $equipment
     */
    public function __construct(Collection $lines, Collection $equipment)
    {
        $this->lines = $lines->values();
        $this->equipment = $equipment->values();
    }

    /**
     * @return array{line_id: int, equipment_id: int|null, score: float, reason: string}|null
     */
    public function suggest(string $optionName): ?array
    {
        return $this->byRule($optionName)
            ?? $this->byPool($optionName)
            ?? $this->bySimilarity($optionName);
    }

    /** @return array{line_id: int, equipment_id: int|null, score: float, reason: string}|null */
    private function byRule(string $name): ?array
    {
        // Skincare lines keep the number in the option name: "Fill TUBE SC 11", "Fill FOUR HEAD SC4".
        if (preg_match('/^(?:Fill|Tube)\b.*\bSC\s*0*(\d{1,2})$/i', $name, $m)) {
            return $this->lineByCode(sprintf('SC-%02d', (int) $m[1]), 0.95, 'rule: SC number');
        }
        // "Fill CUSHION AUTO LINE 5" -> SC-05.
        if (preg_match('/^Fill\b.*\bLINE\s*(\d{1,2})$/i', $name, $m)) {
            return $this->lineByCode(sprintf('SC-%02d', (int) $m[1]), 0.90, 'rule: line number');
        }
        if (preg_match('/^Filling Lipcream (\d)/i', $name, $m)) {
            return $this->lineByCode(sprintf('LC-%02d', (int) $m[1]), 0.95, 'rule: Lipcream number');
        }
        if (preg_match('/^Lipsti(?:ck|k) (\d)$/i', $name, $m)) {
            return $this->lineByCode(sprintf('LS-%02d', (int) $m[1]), 0.95, 'rule: Lipstick number');
        }

        $single = [
            '/^Hot Pouring$/i' => ['HP-01', 0.95, 'rule: Hot Pouring'],
            '/^Eyliner$/i' => ['EL-01', 0.95, 'rule: Eyeliner'],
            '/^Fill Loose Powder$/i' => ['LP-01', 0.95, 'rule: Loose Powder'],
            // Ambiguous in the workbook (MS-01 vs SC-20 "Mascara"): always needs a human.
            '/^Mascara semi auto/i' => ['MS-01', 0.60, 'rule: mascara (MS-01 or SC-20, confirm)'],
        ];
        foreach ($single as $pattern => [$code, $score, $reason]) {
            if (preg_match($pattern, $name)) {
                return $this->lineByCode($code, $score, $reason);
            }
        }

        return null;
    }

    /** @return array{line_id: int, equipment_id: int|null, score: float, reason: string}|null */
    private function byPool(string $name): ?array
    {
        $pool = $this->lines->first(fn (MachineLine $l) => $l->category === 'Packing Equipment');
        if (! $pool) {
            return null;
        }

        foreach (self::POOL_RULES as $pattern => [$needle, $reason]) {
            if (! preg_match($pattern, $name)) {
                continue;
            }
            $machine = $this->equipment->first(fn (Equipment $e) => $e->line_id === $pool->id
                && stripos($e->name, $needle) !== false);

            return [
                'line_id' => $pool->id,
                'equipment_id' => $machine?->id,
                'score' => $machine ? 0.60 : 0.50,
                'reason' => $reason.' (packing pool)',
            ];
        }

        return null;
    }

    /** @return array{line_id: int, equipment_id: int|null, score: float, reason: string}|null */
    private function bySimilarity(string $name): ?array
    {
        $optionTokens = $this->tokens($name);
        if ($optionTokens === []) {
            return null;
        }
        $optionNumbers = array_values(array_filter($optionTokens, fn ($t) => ctype_digit($t)));

        $scored = [];
        foreach ($this->lines as $line) {
            if ($line->category === 'Packing Equipment') {
                continue;
            }
            $lineTokens = $this->tokens($line->name);
            if ($lineTokens === []) {
                continue;
            }
            $jaccard = count(array_intersect($optionTokens, $lineTokens)) / count(array_unique(array_merge($optionTokens, $lineTokens)));
            $digits = preg_match('/(\d+)$/', $line->line_code, $m) ? (string) (int) $m[1] : null;
            $bonus = $digits !== null && in_array($digits, $optionNumbers, true) ? 1 : 0;
            $scored[] = ['line' => $line, 'score' => $jaccard * 0.8 + $bonus * 0.2, 'jaccard' => $jaccard];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        $best = $scored[0] ?? null;
        if (! $best || $best['jaccard'] < self::MIN_SCORE) {
            return null;
        }
        // Two lines equally good: do not guess.
        if (isset($scored[1]) && $best['score'] - $scored[1]['score'] < 0.05) {
            return null;
        }

        $exactMixing = $best['line']->category === 'Mixing' && $best['jaccard'] >= 0.99;

        return [
            'line_id' => $best['line']->id,
            'equipment_id' => null,
            'score' => round($exactMixing ? 1.0 : min(0.85, $best['score']), 2),
            'reason' => $exactMixing ? 'exact mixer name' : 'name similarity',
        ];
    }

    /** @return array{line_id: int, equipment_id: int|null, score: float, reason: string}|null */
    private function lineByCode(string $code, float $score, string $reason): ?array
    {
        $line = $this->lines->first(fn (MachineLine $l) => $l->line_code === $code);

        return $line ? ['line_id' => $line->id, 'equipment_id' => null, 'score' => $score, 'reason' => $reason] : null;
    }

    /** @return array<int, string> */
    private function tokens(string $text): array
    {
        $text = strtolower($text);
        $text = str_replace(['(', ')'], ' ', $text);
        foreach (self::SYNONYMS as $from => $to) {
            $text = str_replace($from, $to, $text);
        }
        preg_match_all('/[a-z]+|\d+[a-z]*/', $text, $found);

        $tokens = array_filter($found[0], fn ($t) => ! in_array($t, self::STOPWORDS, true));

        return array_values(array_unique(array_map(fn ($t) => ctype_digit($t) ? (string) (int) $t : $t, $tokens)));
    }
}
