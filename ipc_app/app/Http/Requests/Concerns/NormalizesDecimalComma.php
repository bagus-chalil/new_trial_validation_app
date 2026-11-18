<?php

namespace App\Http\Requests\Concerns;

/**
 * Decimal numbers are always stored with a dot. If a user (or a locale-happy browser/keyboard)
 * submits a decimal comma ("1,5"), turn it into "1.5" before validation so it is accepted and
 * saved uniformly (user decision, 2026-11-18).
 *
 * Only for fields that are genuinely decimal numbers — never for free-text lists where the comma
 * is a separator (masterbox "4,17,55", no_pallet_qty "1/2016,2/2016").
 */
trait NormalizesDecimalComma
{
    /**
     * @param  list<string>  $paths  top-level keys ("density") or one-level wildcard keys ("samples.*.weight_value")
     */
    protected function normalizeDecimalCommas(array $paths): void
    {
        $input = $this->all();

        foreach ($paths as $path) {
            if (! str_contains($path, '.*.')) {
                if (isset($input[$path]) && is_string($input[$path])) {
                    $input[$path] = $this->commaToDot($input[$path]);
                }

                continue;
            }

            [$group, $field] = explode('.*.', $path, 2);
            if (! isset($input[$group]) || ! is_array($input[$group])) {
                continue;
            }

            foreach ($input[$group] as $key => $row) {
                if (is_array($row) && isset($row[$field]) && is_string($row[$field])) {
                    $input[$group][$key][$field] = $this->commaToDot($row[$field]);
                }
            }
        }

        $this->replace($input);
    }

    private function commaToDot(string $value): string
    {
        return str_replace(',', '.', trim($value));
    }
}
