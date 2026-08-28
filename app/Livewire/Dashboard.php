<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\TransactionType;
use App\Livewire\Concerns\HasPeriodFilter;
use App\Models\Transaction;
use App\Services\CashFlowProjectionService;
use App\Services\CashFlowReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Dashboard')]
class Dashboard extends Component
{
    use HasPeriodFilter;

    /**
     * Horizonte da projeção, em meses.
     */
    #[Url(as: 'projecao', keep: true)]
    public int $projectionMonths = 6;

    public function mount(): void
    {
        $this->mountPeriodFilter();
    }

    public function selectProjectionHorizon(int $months): void
    {
        $this->projectionMonths = in_array($months, CashFlowProjectionService::HORIZONS, true)
            ? $months
            : 6;

        unset($this->projection, $this->charts);

        $this->dispatchChartsUpdate();
    }

    /**
     * Após qualquer registro de movimentação, os números precisam refletir
     * a mudança sem recarregar a página.
     */
    #[On('transaction-saved')]
    #[On('transaction-deleted')]
    public function refreshReports(): void
    {
        unset($this->summary, $this->charts, $this->projection, $this->latestTransactions);

        $this->dispatchChartsUpdate();
    }

    /**
     * Projeção do fluxo de caixa para os próximos meses, calculada a partir
     * das movimentações recorrentes já lançadas.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function projection(): array
    {
        return app(CashFlowProjectionService::class)->project($this->projectionMonths);
    }

    /**
     * @return array{income: float, expense: float, result: float, balance: float, count: int}
     */
    #[Computed]
    public function summary(): array
    {
        [$start, $end] = $this->dateRange();

        return $this->reports()->summary($start, $end);
    }

    /**
     * Payload consumido pelo Alpine para montar/atualizar os quatro gráficos.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function charts(): array
    {
        [$start, $end] = $this->dateRange();
        $reports = $this->reports();

        return [
            'cashFlow' => $reports->timeSeries($start, $end),
            'retained' => $reports->retainedComposition($end),
            'income' => $reports->breakdownBySource(TransactionType::Income, $start, $end),
            'expense' => $reports->breakdownBySource(TransactionType::Expense, $start, $end),
            'projection' => $this->projection,
        ];
    }

    /**
     * @return Collection<int, Transaction>
     */
    #[Computed]
    public function latestTransactions(): Collection
    {
        [$start, $end] = $this->dateRange();

        return Transaction::query()
            ->with('user:id,name')
            ->betweenDates($start, $end)
            ->latest('transaction_date')
            ->latest('id')
            ->limit(8)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.dashboard', [
            'horizons' => CashFlowProjectionService::HORIZONS,
        ]);
    }

    protected function onPeriodChanged(): void
    {
        // A projeção olha para o futuro a partir de hoje: não depende do
        // período filtrado, por isso não é invalidada aqui.
        unset($this->summary, $this->charts, $this->latestTransactions);

        $this->dispatchChartsUpdate();
    }

    /**
     * Os gráficos vivem sob wire:ignore (o Apex controla o próprio DOM), por
     * isso a atualização chega por evento em vez de re-render do Blade.
     */
    private function dispatchChartsUpdate(): void
    {
        $this->dispatch('charts-updated', charts: $this->charts, periodLabel: $this->periodLabel());
    }

    private function reports(): CashFlowReportService
    {
        return app(CashFlowReportService::class);
    }
}
