<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Com que frequência uma despesa recorrente se repete.
 */
enum RecurrenceInterval: string
{
    case Monthly = 'monthly';
    case Bimonthly = 'bimonthly';
    case Quarterly = 'quarterly';
    case FourMonthly = 'four_monthly';
    case Semiannual = 'semiannual';
    case Annual = 'annual';
    case SpecificMonths = 'specific_months';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Mensal',
            self::Bimonthly => 'Bimestral',
            self::Quarterly => 'Trimestral',
            self::FourMonthly => 'Quadrimestral',
            self::Semiannual => 'Semestral',
            self::Annual => 'Anual',
            self::SpecificMonths => 'Meses específicos',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Monthly => 'Todo mês',
            self::Bimonthly => 'A cada 2 meses',
            self::Quarterly => 'A cada 3 meses',
            self::FourMonthly => 'A cada 4 meses',
            self::Semiannual => 'A cada 6 meses',
            self::Annual => 'Uma vez por ano',
            self::SpecificMonths => 'Nos meses que você escolher',
        };
    }

    /**
     * Distância em meses entre duas ocorrências.
     *
     * Nulo para SpecificMonths, cujas datas vêm da lista escolhida pelo
     * usuário em vez de um passo fixo.
     */
    public function monthStep(): ?int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Bimonthly => 2,
            self::Quarterly => 3,
            self::FourMonthly => 4,
            self::Semiannual => 6,
            self::Annual => 12,
            self::SpecificMonths => null,
        };
    }

    public function requiresMonthSelection(): bool
    {
        return $this === self::SpecificMonths;
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
