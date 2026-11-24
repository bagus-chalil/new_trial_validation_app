<?php

namespace App\Http\Requests;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Operator feedback on an AI read ("Beri masukan ke AI"): which field's saved photo to read again and a
 * short hint on where/how to look. Carries the typed form values too (like the status request), so the
 * card re-evaluates against them in the same response.
 */
class StartupAiHintRequest extends StartupAiStatusRequest
{
    public const MAX_LENGTH = 300;

    /** Only fields that have a read of their own (not the 2nd Primary Coding photo, which rides along as file2). */
    public static function hintableFields(): array
    {
        return array_keys(array_filter(config('vision.startup_ai.fields', [])));
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'field' => ['required', 'string', Rule::in(self::hintableFields())],
            'hint' => ['required', 'string', 'min:3', 'max:'.self::MAX_LENGTH],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('hint'))) {
            // One line, no control characters: the text goes into an LLM prompt, not into HTML.
            $this->merge(['hint' => Str::squish(preg_replace('/[\x00-\x1f\x7f]+/u', ' ', $this->input('hint')) ?? '')]);
        }
    }

    public function messages(): array
    {
        return [
            'hint.required' => 'Tulis dulu masukan untuk AI.',
            'hint.min' => 'Masukan terlalu pendek.',
            'hint.max' => 'Masukan maksimal '.self::MAX_LENGTH.' karakter.',
        ];
    }
}
