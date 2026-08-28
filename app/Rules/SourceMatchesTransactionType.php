<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Impede que uma entrada receba uma fonte de saída (e vice-versa).
 *
 * Precisa enxergar o campo `type` do formulário, por isso implementa
 * DataAwareRule em vez de ser uma regra estática.
 */
class SourceMatchesTransactionType implements DataAwareRule, ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $source = $value instanceof TransactionSource
            ? $value
            : TransactionSource::tryFrom((string) $value);

        // Fonte inexistente já é recusada pela regra de enum.
        if ($source === null) {
            return;
        }

        $type = TransactionType::tryFrom((string) ($this->data['type'] ?? ''));

        if ($type === null || $source->type() === $type) {
            return;
        }

        $fail(sprintf(
            'A fonte selecionada não é válida para uma %s.',
            mb_strtolower($type->label()),
        ));
    }
}
