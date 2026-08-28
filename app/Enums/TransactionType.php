<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Natureza de uma movimentação no fluxo de caixa.
 */
enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::Income => 'Entrada',
            self::Expense => 'Saída',
        };
    }

    /**
     * Sinal aplicado ao valor no cálculo do saldo.
     */
    public function signal(): int
    {
        return match ($this) {
            self::Income => 1,
            self::Expense => -1,
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Income => '#628B72',
            self::Expense => '#B4593F',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static fn (array $carry, self $case): array => $carry + [$case->value => $case->label()],
            [],
        );
    }
}
