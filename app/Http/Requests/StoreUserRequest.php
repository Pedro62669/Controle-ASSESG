<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Validation\UserValidation;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    /**
     * Cadastro de usuários é exclusivo do administrador principal.
     */
    public function authorize(): bool
    {
        return $this->user()?->isMainAdmin() === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return UserValidation::rules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return UserValidation::messages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return UserValidation::attributes();
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => mb_strtolower(trim((string) $this->input('email'))),
            ]);
        }
    }
}
