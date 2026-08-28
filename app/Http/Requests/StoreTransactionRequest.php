<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Validation\TransactionValidation;
use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    /**
     * Qualquer usuário autenticado e ativo pode registrar movimentações.
     */
    public function authorize(): bool
    {
        return $this->user()?->is_active === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return TransactionValidation::rules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return TransactionValidation::messages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return TransactionValidation::attributes();
    }

    /**
     * Normaliza o valor digitado no padrão brasileiro (1.234,56) antes de validar.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('amount') && is_string($this->input('amount'))) {
            $this->merge([
                'amount' => str_replace(',', '.', str_replace('.', '', $this->string('amount')->value())),
            ]);
        }

        if ($this->has('description')) {
            $description = trim((string) $this->input('description'));

            $this->merge([
                'description' => $description === '' ? null : $description,
            ]);
        }
    }
}
