<?php

namespace App\Services\Ai;

/**
 * Heuristic detector for free-text values that are test/keyboard-mash noise
 * ("tes", "sdfsd", "xxxx") rather than real operator input. Deliberately
 * conservative: short but legitimate answers ("OK", "N/A", "Conform") pass.
 * Empty text is "missing", not junk.
 */
class TextQuality
{
    /** Short values that are valid answers in this domain. */
    private const ALLOWED = [
        'ok', 'oke', 'okay', 'na', 'n/a', 'no', 'yes', 'ya', 'good', 'pass', 'conform', 'ng', 'not ok', 'nok',
    ];

    private const BLOCKLIST = [
        'tes', 'test', 'testing', 'tst', 'asd', 'asdf', 'asdfg', 'sdf', 'sdfsd', 'sdfs', 'dfg',
        'qwe', 'qwerty', 'zxc', 'xxx', 'xx', 'aaa', 'abc', 'abcd', 'berak', 'bla', 'blabla',
    ];

    /**
     * @return string|null a short reason when the text looks like junk, null when it looks fine or is empty
     */
    public static function junkReason(?string $text): ?string
    {
        $value = mb_strtolower(trim((string) $text));
        if ($value === '' || in_array($value, self::ALLOWED, true)) {
            return null;
        }

        if (in_array($value, self::BLOCKLIST, true)) {
            return 'known placeholder word';
        }

        if (preg_match('/^(.)\1{3,}$/u', $value)) {
            return 'repeated character';
        }

        if (preg_match('/^[a-z]{3,}$/', $value)) {
            if (preg_match('/(asdf|sdfs|qwer|wert|zxcv|dfgh|ghjk|hjkl|xcvb)/', $value)) {
                return 'keyboard mash';
            }
            // 5+ letters only: shorter vowel-less words are usually real acronyms (NPD, PRD, QAC).
            if (strlen($value) >= 5 && ! preg_match('/[aeiou]/', $value)) {
                return 'no vowels';
            }
        }

        return null;
    }

    public static function isJunk(?string $text): bool
    {
        return self::junkReason($text) !== null;
    }
}
