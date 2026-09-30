<?php

namespace App\Http\Requests\Trials;

use App\Models\Trial;
use App\Models\TrialAdditionalAttachment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class StoreTrialAdditionalAttachmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $trial = Trial::whereNull('deleted_at')->findOrFail((int) $this->route('trial'));

        return Gate::allows('uploadAdditionalAttachment', $trial);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $extensions = implode(',', array_unique(array_merge(
            array_values(TrialAdditionalAttachment::ALLOWED_MIME_TO_EXTENSION),
            ['jpeg'],
        )));

        return [
            'files' => ['required', 'array', 'min:1', 'max:'.TrialAdditionalAttachment::MAX_PER_TRIAL],
            'files.*' => [
                'file',
                'max:'.TrialAdditionalAttachment::MAX_SIZE_KB,
                'mimetypes:'.implode(',', array_keys(TrialAdditionalAttachment::ALLOWED_MIME_TO_EXTENSION)),
                'extensions:'.$extensions,
            ],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * The per-trial cap counts files already stored, so it can't be a plain
     * `max:` rule on the submitted array alone.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $existing = TrialAdditionalAttachment::where('trial_id', (int) $this->route('trial'))->count();
            $remaining = TrialAdditionalAttachment::MAX_PER_TRIAL - $existing;
            $submitted = count((array) $this->file('files', []));

            if ($submitted > $remaining) {
                $validator->errors()->add('files', $remaining > 0
                    ? 'Maksimal '.TrialAdditionalAttachment::MAX_PER_TRIAL." attachment per trial — sisa slot {$remaining}."
                    : 'Batas '.TrialAdditionalAttachment::MAX_PER_TRIAL.' additional attachment untuk trial ini sudah tercapai.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.required' => 'Pilih minimal satu file.',
            'files.max' => 'Maksimal '.TrialAdditionalAttachment::MAX_PER_TRIAL.' file per upload.',
            'files.*.max' => 'Ukuran file maksimal 10 MB.',
            'files.*.mimetypes' => 'Hanya file PDF atau gambar (JPG, PNG, WEBP, GIF) yang diizinkan.',
            'files.*.extensions' => 'Hanya file PDF atau gambar (JPG, PNG, WEBP, GIF) yang diizinkan.',
        ];
    }
}
