<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Por quanto tempo uma despesa recorrente continua acontecendo.
 */
enum RecurrenceDuration: string
{
    case Indeterminate = 'indeterminate';
    case Installments = 'installments';

    public function label(): string
    {
        return match ($this) {
            self::Indeterminate => 'Por tempo indeterminado',
            self::Installments => 'Número definido de parcelas',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Indeterminate => 'Segue até ser cancelada — internet, aluguel, contabilidade.',
            self::Installments => 'Termina após um número conhecido de cobranças.',
        };
    }

    public function requiresCount(): bool
    {
        return $this === self::Installments;
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
