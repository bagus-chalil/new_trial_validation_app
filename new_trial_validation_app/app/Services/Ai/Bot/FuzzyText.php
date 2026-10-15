<?php

namespace App\Services\Ai\Bot;

/**
 * Small typo-tolerant text comparison shared by AliasSuggester and
 * QuestionClusterer. Pure, no model, no network: tokens are matched exactly,
 * by containment (Indonesian suffixes, Korean particles) or by edit distance.
 */
class FuzzyText
{
    /** Words that carry no intent ("trial" is in nearly every question). */
    private const STOPWORDS = [
        'yang', 'di', 'ke', 'dan', 'apa', 'ini', 'itu', 'the', 'an', 'of', 'to', 'is', 'are', 'me', 'dong', 'ya',
        'tolong', 'please', 'pls', 'trial', 'trials', '트라이얼',
    ];

    /** Minimum similarity for two different tokens to count as the same word. */
    private const TOKEN_THRESHOLD = 0.75;

    /** @return array<int, string> */
    public static function tokens(string $text): array
    {
        $parts = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(
            $parts,
            fn (string $t) => mb_strlen($t) >= 2 && ! in_array($t, self::STOPWORDS, true),
        ));
    }

    /** 1 for equal tokens, otherwise a value >= TOKEN_THRESHOLD for near matches, else 0. */
    public static function tokenSimilarity(string $a, string $b): float
    {
        if ($a === $b) {
            return 1.0;
        }

        $la = mb_strlen($a);
        $lb = mb_strlen($b);
        $hangul = preg_match('/\p{Hangul}/u', $a.$b) === 1;
        $shortest = min($la, $lb);

        if ($shortest >= ($hangul ? 2 : 4) && (str_contains($a, $b) || str_contains($b, $a))) {
            return 0.85;
        }

        if ($shortest < 4 || $hangul) {
            return 0.0;
        }

        $similarity = 1 - self::distance($a, $b) / max($la, $lb);

        return $similarity >= self::TOKEN_THRESHOLD ? $similarity : 0.0;
    }

    /**
     * How well every token of $needle is found in $haystack (0..1).
     *
     * @param  array<int, string>  $needle
     * @param  array<int, string>  $haystack
     */
    public static function coverage(array $needle, array $haystack): float
    {
        if ($needle === [] || $haystack === []) {
            return 0.0;
        }

        return self::bestSum($needle, $haystack) / count($needle);
    }

    /**
     * Symmetric similarity between two token lists (0..1).
     *
     * @param  array<int, string>  $a
     * @param  array<int, string>  $b
     */
    public static function similarity(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }

        return (self::bestSum($a, $b) + self::bestSum($b, $a)) / (count($a) + count($b));
    }

    /**
     * @param  array<int, string>  $from
     * @param  array<int, string>  $in
     */
    private static function bestSum(array $from, array $in): float
    {
        $sum = 0.0;
        foreach ($from as $token) {
            $best = 0.0;
            foreach ($in as $other) {
                $best = max($best, self::tokenSimilarity($token, $other));
            }
            $sum += $best;
        }

        return $sum;
    }

    /** Multibyte-safe Levenshtein distance (PHP's built-in one works on bytes). */
    private static function distance(string $a, string $b): int
    {
        $x = preg_split('//u', $a, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $y = preg_split('//u', $b, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $previous = range(0, count($y));
        foreach ($x as $i => $cx) {
            $current = [$i + 1];
            foreach ($y as $j => $cy) {
                $current[] = min($previous[$j + 1] + 1, $current[$j] + 1, $previous[$j] + ($cx === $cy ? 0 : 1));
            }
            $previous = $current;
        }

        return $previous[count($y)];
    }
}
