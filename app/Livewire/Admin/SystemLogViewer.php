<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\LogAction;
use App\Livewire\Concerns\HasPeriodFilter;
use App\Models\SystemLog;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Logs do sistema — área exclusiva do administrador principal
 * (protegida pelo middleware main.admin nas rotas).
 */
#[Title('Logs do Sistema')]
class SystemLogViewer extends Component
{
    use HasPeriodFilter;
    use WithPagination;

    #[Url(as: 'busca')]
    public string $search = '';

    #[Url(as: 'acao')]
    public string $actionFilter = '';

    #[Url(as: 'recurso')]
    public string $resourceFilter = '';

    #[Url(as: 'usuario')]
    public ?int $userFilter = null;

    public ?int $expandedLog = null;

    public function mount(): void
    {
        $this->period = 'month';
        $this->mountPeriodFilter();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedActionFilter(): void
    {
        $this->resetPage();
    }

    public function updatedResourceFilter(): void
    {
        $this->resetPage();
    }

    public function updatedUserFilter(): void
    {
        $this->resetPage();
    }

    public function toggle(int $logId): void
    {
        $this->expandedLog = $this->expandedLog === $logId ? null : $logId;
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'actionFilter', 'resourceFilter', 'userFilter']);
        $this->selectPeriod('month');
    }

    /**
     * @return LengthAwarePaginator<int, SystemLog>
     */
    public function logs(): LengthAwarePaginator
    {
        [$start, $end] = $this->dateRange();

        return SystemLog::query()
            ->with('user:id,name')
            ->whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->search($this->search)
            ->when(
                LogAction::tryFrom($this->actionFilter) !== null,
                fn (Builder $query) => $query->where('action', $this->actionFilter),
            )
            ->when(
                $this->resourceFilter !== '',
                fn (Builder $query) => $query->where('loggable_type', $this->resourceFilter),
            )
            ->when(
                $this->userFilter !== null,
                fn (Builder $query) => $query->where('user_id', $this->userFilter),
            )
            ->latest('created_at')
            ->latest('id')
            ->paginate(20);
    }

    public function render(): View
    {
        return view('livewire.admin.system-log-viewer', [
            'logs' => $this->logs(),
            'actions' => LogAction::cases(),
            'resources' => [
                Transaction::class => 'Transações',
                User::class => 'Usuários',
            ],
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    protected function onPeriodChanged(): void
    {
        $this->resetPage();
    }
}
