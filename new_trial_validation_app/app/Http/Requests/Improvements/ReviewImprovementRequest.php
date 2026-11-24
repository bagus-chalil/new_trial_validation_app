<?php

namespace App\Http\Requests\Improvements;

use App\Models\Improvement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewImprovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $improvement = Improvement::whereNull('deleted_at')->findOrFail((int) $this->route('improvement'));

        return (bool) $this->user()?->can('review', $improvement);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(Improvement::STATUSES)],
            'decision' => ['required', Rule::in(Improvement::DECISIONS)],
            'pi_owner' => ['nullable', 'string', 'max:100'],
            'pi_feedback' => ['nullable', 'string', 'max:5000'],
            'pi_suggestion' => ['nullable', 'string', 'max:5000'],
            'pi_action' => ['nullable', 'string', 'max:5000'],
            'pi_reason' => ['nullable', 'string', 'max:5000'],
            'after_condition' => ['nullable', 'string', 'max:5000'],
            'after_result' => ['nullable', 'string', 'max:5000'],
            'after_date' => ['nullable', 'date'],
            'after_pic' => ['nullable', 'string', 'max:100'],
        ];
    }
}
