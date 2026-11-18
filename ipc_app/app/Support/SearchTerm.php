<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * One definition of "free-text search" for every list/lookup in the app (2026-11-18, user
 * request: typing just "BLB" must find "26Y252/BLB" — match anywhere, not only from the start).
 *
 * The term is split on whitespace and "/" into at most MAX_TOKENS words; every word must appear
 * *somewhere* (any position, any order) in at least one of the searched columns. So "blb 26y252",
 * "252/blb" and "shampoo moell" all work, while an unrelated word still narrows the result.
 * `%` / `_` / `\` typed by the user are escaped, so they are matched literally.
 *
 * Callers are responsible for keeping the scanned set small: use it on small tables, or on a
 * covered index with a cap (see IpcBatch::noBatchIdsMatching) — never on a wide, huge table.
 */
final class SearchTerm
{
    public const MAX_TOKENS = 6;

    /** @return list<string> */
    public static function tokens(string $q): array
    {
        $parts = preg_split('/[\s\/]+/u', trim($q), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_slice(array_values($parts), 0, self::MAX_TOKENS);
    }

    public static function like(string $token): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $token).'%';
    }

    /**
     * AND over words, OR over columns. A term with no usable word adds no condition.
     *
     * @param  list<string>  $columns
     */
    public static function apply(EloquentBuilder|QueryBuilder $query, string $q, array $columns): void
    {
        foreach (self::tokens($q) as $token) {
            $like = self::like($token);

            $query->where(function ($word) use ($columns, $like) {
                foreach ($columns as $column) {
                    $word->orWhere($column, 'like', $like);
                }
            });
        }
    }

    /** Same rule for rows already in memory (small, already-loaded collections only). */
    public static function matches(string $haystack, string $q): bool
    {
        $haystack = mb_strtolower($haystack);

        foreach (self::tokens($q) as $token) {
            if (! str_contains($haystack, mb_strtolower($token))) {
                return false;
            }
        }

        return true;
    }
}
