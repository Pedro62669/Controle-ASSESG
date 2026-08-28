<?php

declare(strict_types=1);

namespace App\Livewire\Transactions;

use App\Enums\TransactionType;
use App\Livewire\Concerns\HasPeriodFilter;
use App\Models\Transaction;
use App\Services\CashFlowReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Movimentações')]
class TransactionList extends Component
{
    use HasPeriodFilter;
    use WithPagination;

    #[Url(as: 'busca')]
    public string $search = '';

    #[Url(as: 'tipo')]
    public string $typeFilter = '';

    #[Url(as: 'anexo')]
    public string $documentFilter = '';

    public ?int $confirmingDeletionOf = null;

    public function mount(): void
    {
        $this->mountPeriodFilter();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDocumentFilter(): void
    {
        $this->resetPage();
    }

    #[On('transaction-saved')]
    public function refreshList(): void
    {
        unset($this->summary);
        $this->resetPage();
    }

    public function confirmDeletion(int $transactionId): void
    {
        $this->confirmingDeletionOf = $transactionId;
    }

    public function cancelDeletion(): void
    {
        $this->confirmingDeletionOf = null;
    }

    /**
     * Exclusão lógica: o histórico permanece auditável em system_logs e o
     * comprovante segue disponível para conferência posterior.
     */
    public function delete(int $transactionId): void
    {
        $transaction = Transaction::query()->findOrFail($transactionId);
        $user = Auth::user();

        abort_unless(
            $user !== null && ($user->isMainAdmin() || $transaction->user_id === $user->getKey()),
            403,
            'Você não tem permissão para excluir esta movimentação.',
        );

        $transaction->delete();

        $this->confirmingDeletionOf = null;

        unset($this->summary);

        $this->dispatch('transaction-deleted');
        $this->dispatch('notify', type: 'success', message: 'Movimentação excluída.');
    }

    /**
     * @return array{income: float, expense: float, result: float, balance: float, count: int}
     */
    #[Computed]
    public function summary(): array
    {
        [$start, $end] = $this->dateRange();

        return app(CashFlowReportService::class)->summary($start, $end);
    }

    /**
     * @return LengthAwarePaginator<int, Transaction>
     */
    public function transactions(): LengthAwarePaginator
    {
        [$start, $end] = $this->dateRange();

        return Transaction::query()
            ->with('user:id,name')
            ->betweenDates($start, $end)
            ->when($this->search !== '', function (Builder $query): void {
                $term = trim($this->search);

                $query->where(function (Builder $query) use ($term): void {
                    $query->where('description', 'like', "%{$term}%")
                        ->orWhere('document_name', 'like', "%{$term}%")
                        ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', "%{$term}%"));
                });
            })
            ->when(
                TransactionType::tryFrom($this->typeFilter) !== null,
                fn (Builder $query) => $query->where('type', $this->typeFilter),
            )
            ->when($this->documentFilter === 'with', fn (Builder $query) => $query->whereNotNull('document_path'))
            ->when($this->documentFilter === 'without', fn (Builder $query) => $query->whereNull('document_path'))
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.transactions.transaction-list', [
            'transactions' => $this->transactions(),
            'types' => TransactionType::cases(),
        ]);
    }

    protected function onPeriodChanged(): void
    {
        unset($this->summary);
        $this->resetPage();
    }
}
