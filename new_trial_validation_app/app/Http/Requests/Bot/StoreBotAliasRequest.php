<?php

namespace App\Http\Requests\Bot;

use App\Models\AiBotAlias;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Teach the trial bot a phrase: it may only point at a fixed intent (or be ignored). */
class StoreBotAliasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-settings');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'phrase' => ['required', 'string', 'max:200'],
            'target' => ['required', Rule::in([...AiBotAlias::TARGETS, AiBotAlias::TARGET_IGNORE])],
            'status' => ['nullable', Rule::in(AiBotAlias::STATUSES)],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function ($validator) {
            // Two letters is too short to be a safe keyword: it would match inside unrelated words.
            if (mb_strlen(AiBotAlias::normalize((string) $this->input('phrase'))) < 3) {
                $validator->errors()->add('phrase', __('messages.validation.bot_alias_too_short'));
            }
        }];
    }
}
