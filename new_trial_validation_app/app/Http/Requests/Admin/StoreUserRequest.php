<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        // Uniqueness is only enforced when `id` is present (a real edit,
        // identified by row — see UserController::store()): that's the only
        // case where the submitted email could collide with a *different*
        // user's row. The legacy create-or-update-by-email flow (no `id`
        // sent) deliberately matches an existing row by email on purpose, so
        // enforcing uniqueness there would reject the exact upsert it's
        // meant to do.
        $emailRules = ['required', 'email', 'max:191'];
        if ($this->filled('id')) {
            $emailRules[] = Rule::unique('users', 'email')->ignore($this->input('id'));
        }

        return [
            'id' => ['nullable', 'integer', 'exists:users,id'],
            'name' => ['required', 'string', 'max:191'],
            'email' => $emailRules,
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);
        $data['role'] = trim($data['role']);

        return $data;
    }
}
