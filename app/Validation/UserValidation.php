<?php

declare(strict_types=1);

namespace App\Validation;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Fonte única das regras de cadastro de usuários, compartilhada entre o
 * Form Request e o componente Livewire de gestão de usuários.
 */
final class UserValidation
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(?User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->getKey()),
            ],
            // Na edição a senha só é validada quando o campo é preenchido.
            'password' => [
                $user === null ? 'required' : 'nullable',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
            'is_main_admin' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do usuário.',
            'name.min' => 'O nome deve ter no mínimo 3 caracteres.',
            'email.required' => 'Informe o e-mail do usuário.',
            'email.email' => 'Informe um e-mail válido.',
            'email.unique' => 'Este e-mail já está cadastrado.',
            'password.required' => 'Informe uma senha para o novo usuário.',
            'password.confirmed' => 'A confirmação de senha não confere.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'name' => 'nome',
            'email' => 'e-mail',
            'password' => 'senha',
            'is_main_admin' => 'administrador principal',
            'is_active' => 'situação',
        ];
    }
}
