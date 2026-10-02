<?php

namespace App\Services\Localization;

use Illuminate\Support\Arr;

/**
 * The UI languages a user can switch between from the header, and the
 * translation strings handed to the React frontend.
 *
 * Strings live in Laravel's own group files (lang/{locale}/{group}.php), so
 * the same `dashboard.title` key works as `__('dashboard.title')` in PHP and
 * `t('dashboard.title')` in React.
 */
class Locales
{
    public const SUPPORTED = ['id', 'en', 'ko'];

    public const DEFAULT = 'id';

    public const COOKIE = 'locale';

    /**
     * Laravel's own framework groups: used server-side only, never shipped
     * to the browser.
     */
    private const SERVER_ONLY_GROUPS = ['auth', 'pagination', 'passwords', 'validation'];

    /**
     * @phpstan-assert-if-true string $locale
     */
    public static function isSupported(mixed $locale): bool
    {
        return is_string($locale) && in_array($locale, self::SUPPORTED, true);
    }

    /**
     * Flattened `group.key => string` map for $locale, with Indonesian (the
     * app's original language) filling any key the locale hasn't translated.
     *
     * @return array<string, string>
     */
    public static function frontendStrings(string $locale): array
    {
        $strings = self::load(self::DEFAULT);

        if ($locale !== self::DEFAULT) {
            $strings = array_merge($strings, self::load($locale));
        }

        return $strings;
    }

    /**
     * @return array<string, string>
     */
    private static function load(string $locale): array
    {
        $strings = [];

        foreach (glob(lang_path($locale.'/*.php')) ?: [] as $file) {
            $group = basename($file, '.php');

            if (in_array($group, self::SERVER_ONLY_GROUPS, true)) {
                continue;
            }

            $lines = require $file;

            if (is_array($lines)) {
                foreach (Arr::dot($lines) as $key => $value) {
                    if (is_string($value)) {
                        $strings[$group.'.'.$key] = $value;
                    }
                }
            }
        }

        return $strings;
    }
}
