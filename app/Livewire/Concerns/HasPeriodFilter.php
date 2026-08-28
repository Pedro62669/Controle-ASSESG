<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Enums\PeriodFilter;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

/**
 * Filtro global de período compartilhado pelo dashboard e pelas listagens.
 *
 * O estado vai para a query string, de modo que um período filtrado possa
 * ser compartilhado por link e sobreviva ao refresh da página.
 */
trait HasPeriodFilter
{
    #[Url(as: 'periodo', keep: true)]
    public string $period = 'month';

    #[Url(as: 'inicio', keep: true)]
    public ?string $startDate = null;

    #[Url(as: 'fim', keep: true)]
    public ?string $endDate = null;

    public function mountPeriodFilter(): void
    {
        $resolved = $this->periodFilter();

        if ($resolved->requiresCustomDates()) {
            [$start, $end] = $resolved->resolve($this->startDate, $this->endDate);

            $this->startDate ??= $start->toDateString();
            $this->endDate ??= $end->toDateString();
        }
    }

    public function periodFilter(): PeriodFilter
    {
        return PeriodFilter::tryFrom($this->period) ?? PeriodFilter::Month;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function dateRange(): array
    {
        return $this->periodFilter()->resolve($this->startDate, $this->endDate);
    }

    public function periodLabel(): string
    {
        [$start, $end] = $this->dateRange();

        if ($start->isSameDay($end)) {
            return $start->format('d/m/Y');
        }

        return $start->format('d/m/Y').' até '.$end->format('d/m/Y');
    }

    /**
     * Troca o período pré-definido; ao sair do customizado, limpa as datas
     * manuais para não deixar resíduo na URL.
     */
    public function selectPeriod(string $period): void
    {
        $this->period = PeriodFilter::tryFrom($period)?->value ?? PeriodFilter::Month->value;

        if (! $this->periodFilter()->requiresCustomDates()) {
            $this->startDate = null;
            $this->endDate = null;
        } else {
            [$start, $end] = PeriodFilter::Month->resolve();

            $this->startDate ??= $start->toDateString();
            $this->endDate ??= $end->toDateString();
        }

        $this->onPeriodChanged();
    }

    public function updatedStartDate(): void
    {
        $this->onPeriodChanged();
    }

    public function updatedEndDate(): void
    {
        $this->onPeriodChanged();
    }

    /**
     * Gancho para o componente reagir à mudança de período.
     */
    protected function onPeriodChanged(): void
    {
        //
    }
}
