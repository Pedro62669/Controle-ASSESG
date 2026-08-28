<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Support\Carbon;

/**
 * Períodos pré-definidos do filtro global do dashboard.
 */
enum PeriodFilter: string
{
    case Today = 'today';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Today => 'Hoje',
            self::Week => 'Esta Semana',
            self::Month => 'Este Mês',
            self::Year => 'Este Ano',
            self::Custom => 'Período Customizado',
        };
    }

    public function requiresCustomDates(): bool
    {
        return $this === self::Custom;
    }

    /**
     * Resolve o intervalo de datas do período.
     *
     * Para o período customizado, as datas informadas pelo usuário são
     * usadas; datas inválidas ou ausentes caem no mês corrente.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function resolve(?string $startDate = null, ?string $endDate = null): array
    {
        return match ($this) {
            self::Today => [Carbon::today(), Carbon::today()],
            self::Week => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()],
            self::Month => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
            self::Year => [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()],
            self::Custom => self::resolveCustom($startDate, $endDate),
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function resolveCustom(?string $startDate, ?string $endDate): array
    {
        $start = self::parse($startDate) ?? Carbon::now()->startOfMonth();
        $end = self::parse($endDate) ?? Carbon::now()->endOfMonth();

        // Intervalo invertido pelo usuário é corrigido em vez de retornar vazio.
        return $start->greaterThan($end)
            ? [$end->startOfDay(), $start->endOfDay()]
            : [$start->startOfDay(), $end->endOfDay()];
    }

    private static function parse(?string $date): ?Carbon
    {
        if (blank($date)) {
            return null;
        }

        try {
            return Carbon::parse($date);
        } catch (\Throwable) {
            return null;
        }
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
