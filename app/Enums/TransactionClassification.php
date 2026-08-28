<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Distingue o lançamento avulso do que se repete no tempo.
 *
 * Vale para entradas e saídas: tanto um auxílio mensal do poder público
 * quanto o aluguel da sede são movimentações recorrentes.
 */
enum TransactionClassification: string
{
    case OneOff = 'one_off';
    case Recurring = 'recurring';

    public function label(): string
    {
        return match ($this) {
            self::OneOff => 'Pontual',
            self::Recurring => 'Recorrente',
        };
    }

    /**
     * Rótulo completo, com o substantivo do tipo ("Entrada Recorrente").
     */
    public function labelFor(TransactionType $type): string
    {
        $noun = $type === TransactionType::Income ? 'Entrada' : 'Despesa';

        return $noun.' '.$this->label();
    }

    public function descriptionFor(TransactionType $type): string
    {
        if ($type === TransactionType::Income) {
            return match ($this) {
                self::OneOff => 'Recebimento avulso, sem repetição prevista.',
                self::Recurring => 'Repasse ou doação que se repete periodicamente.',
            };
        }

        return match ($this) {
            self::OneOff => 'Gasto eventual, sem repetição prevista.',
            self::Recurring => 'Compromisso que se repete periodicamente.',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::OneOff => 'bg-accent-100 text-accent-800',
            self::Recurring => 'bg-primary-100 text-primary-800',
        };
    }

    public function isRecurring(): bool
    {
        return $this === self::Recurring;
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
