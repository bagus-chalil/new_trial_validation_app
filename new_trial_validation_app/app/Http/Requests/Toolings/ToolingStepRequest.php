<?php

namespace App\Http\Requests\Toolings;

use App\Models\Tooling;
use App\Support\ToolingOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * One Check Tooling step (route `projects.toolings.steps`, `{step}` segment).
 * Each step maps to a ToolingPolicy ability and its own rules; the four
 * signature steps also require the user's own account password as an
 * e-signature, verified like the Trial Approval (decision 2026-11-23).
 */
class ToolingStepRequest extends FormRequest
{
    /** step => [policy ability, needs e-signature password] */
    public const STEPS = [
        'sign-prni' => ['signPrni', true],
        'send-sample' => ['sendSample', false],
        'sign-rni' => ['signRni', true],
        'receive-sample' => ['receiveSample', false],
        'pie-check' => ['pieCheck', true],
        'sign-production' => ['signProduction', true],
        'hold' => ['hold', false],
        'reject' => ['reject', false],
        'resume' => ['resume', false],
    ];

    private ?Tooling $tooling = null;

    public function tooling(): Tooling
    {
        return $this->tooling ??= Tooling::whereNull('deleted_at')->where('id', (int) $this->route('tooling'))->firstOrFail();
    }

    public function step(): string
    {
        return (string) $this->route('step');
    }

    public function authorize(): bool
    {
        $ability = self::STEPS[$this->step()][0] ?? null;

        return $ability !== null && (bool) $this->user()?->can($ability, $this->tooling());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $note = ['nullable', 'string', 'max:1000'];
        $rules = match ($this->step()) {
            'sign-prni', 'sign-production', 'resume' => ['note' => $note],
            'send-sample' => ['date' => ['required', 'date'], 'note' => $note],
            'receive-sample' => ['date' => ['required', 'date'], 'note' => $note],
            'sign-rni' => [
                'formula_info' => ['required', 'string', 'max:5000'],
                'volume_suggest' => ['required', 'string', 'max:255'],
                'note' => $note,
            ],
            'pie-check' => [
                'tools' => ['required', 'array', 'min:1', 'max:30'],
                'tools.*.tool' => ['nullable', 'string', 'max:160'],
                'tools.*.actual' => ['nullable', 'string', 'max:255'],
                'tools.*.status' => ['nullable', Rule::in(ToolingOptions::TOOL_STATUSES)],
                'recommended_machine' => ['required', 'string', 'max:255'],
                'need_invest' => ['required', Rule::in(ToolingOptions::INVEST_VALUES)],
                'need_invest_note' => ['nullable', 'string', 'max:5000'],
                'production_note' => ['nullable', 'string', 'max:5000'],
                'risk_level' => ['required', Rule::in(ToolingOptions::RISK_LEVELS)],
                'note' => $note,
            ],
            'hold', 'reject' => ['reason' => ['required', 'string', 'max:2000']],
            default => [],
        };

        if (self::STEPS[$this->step()][1] ?? false) {
            $rules['signature_password'] = ['required', 'string'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! (self::STEPS[$this->step()][1] ?? false)) {
                return;
            }

            $password = (string) $this->input('signature_password');
            if ($password === '') {
                return;
            }

            $user = $this->user();
            if (! $user->is_active || ! Hash::check($password, $user->password_hash)) {
                $validator->errors()->add('signature_password', __('messages.validation.signature_wrong'));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'date' => __('toolings.fields.date'),
            'formula_info' => __('toolings.fields.formula_info'),
            'volume_suggest' => __('toolings.fields.volume_suggest'),
            'recommended_machine' => __('toolings.fields.recommended_machine'),
            'need_invest' => __('toolings.fields.need_invest'),
            'risk_level' => __('toolings.fields.risk_level'),
            'reason' => __('toolings.fields.reason'),
            'signature_password' => __('toolings.fields.signature_password'),
            'tools' => __('toolings.fields.tools'),
        ];
    }
}
