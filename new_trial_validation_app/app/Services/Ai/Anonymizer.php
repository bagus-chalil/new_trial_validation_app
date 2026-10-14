<?php

namespace App\Services\Ai;

/**
 * Stable one-way pseudonyms for people (HMAC keyed with the app key), so the
 * same person is the same token across exports without exposing the email.
 * Free text typed by users is NOT scrubbed — names inside comments remain.
 */
class Anonymizer
{
    public function __construct(private readonly string $key) {}

    public static function make(): self
    {
        return new self((string) config('app.key'));
    }

    public function person(?string $identity): ?string
    {
        $identity = mb_strtolower(trim((string) $identity));
        if ($identity === '') {
            return null;
        }

        return 'u_'.substr(hash_hmac('sha256', 'ai-export-v1|'.$identity, $this->key), 0, 12);
    }
}
