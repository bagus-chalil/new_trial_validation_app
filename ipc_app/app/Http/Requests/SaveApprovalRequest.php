<?php

namespace App\Http\Requests;

use App\Models\IpcApproval;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rejected = $this->input('decision') === IpcApproval::DECISION_REJECTED;

        return [
            'decision' => ['required', Rule::in(IpcApproval::DECISIONS)],
            'remarks' => [$rejected ? 'required' : 'nullable', 'string'],
        ];
    }
}
