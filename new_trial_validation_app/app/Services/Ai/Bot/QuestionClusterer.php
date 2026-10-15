<?php

namespace App\Services\Ai\Bot;

/**
 * Merges near-identical unanswered questions ("kenapa trial gagal" / "knp trial
 * gagal?") into one cluster so an admin teaches them once. The representative
 * is the most asked wording; the others are listed as variants.
 */
class QuestionClusterer
{
    public const THRESHOLD = 0.6;

    /**
     * @param  array<int, array{question:string, asked_count:int, ids:array<int, int>, last_asked_at:string, locale:string}>  $groups  one per distinct wording
     * @return array<int, array{question:string, asked_count:int, ids:array<int, int>, last_asked_at:string, locale:string, variants:array<int, array{question:string, asked_count:int}>}>
     */
    public function cluster(array $groups): array
    {
        usort($groups, fn (array $a, array $b) => [$b['asked_count'], $b['last_asked_at']] <=> [$a['asked_count'], $a['last_asked_at']]);

        /** @var array<int, array<int, string>> $tokens representative tokens per cluster */
        $tokens = [];
        /** @var array<int, array<int, array{question:string, asked_count:int}>> $variants */
        $variants = [];
        /** @var array<int, int> $counts */
        $counts = [];
        /** @var array<int, array<int, int>> $idLists */
        $idLists = [];
        /** @var array<int, string> $lasts */
        $lasts = [];
        /** @var array<int, array{question:string, locale:string}> $heads */
        $heads = [];

        foreach ($groups as $group) {
            $groupTokens = FuzzyText::tokens($group['question']);
            $target = null;

            if ($groupTokens !== []) {
                foreach ($tokens as $index => $representative) {
                    if (FuzzyText::similarity($groupTokens, $representative) >= self::THRESHOLD) {
                        $target = $index;
                        break;
                    }
                }
            }

            if ($target === null) {
                $target = count($heads);
                $heads[] = ['question' => $group['question'], 'locale' => $group['locale']];
                $tokens[] = $groupTokens;
                $variants[] = [];
                $counts[] = 0;
                $idLists[] = [];
                $lasts[] = '';
            } else {
                $variants[$target][] = ['question' => $group['question'], 'asked_count' => $group['asked_count']];
            }

            $counts[$target] += $group['asked_count'];
            $idLists[$target] = [...$idLists[$target], ...$group['ids']];
            $lasts[$target] = max($lasts[$target], $group['last_asked_at']);
        }

        $rows = [];
        foreach ($heads as $index => $head) {
            $rows[] = [
                'question' => $head['question'],
                'asked_count' => $counts[$index],
                'ids' => $idLists[$index],
                'last_asked_at' => $lasts[$index],
                'locale' => $head['locale'],
                'variants' => $variants[$index],
            ];
        }

        usort($rows, fn (array $a, array $b) => [$b['asked_count'], $b['last_asked_at']] <=> [$a['asked_count'], $a['last_asked_at']]);

        return $rows;
    }
}
