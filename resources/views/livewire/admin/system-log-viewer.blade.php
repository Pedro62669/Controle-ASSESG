@php
    use App\Enums\PeriodFilter;

    $fieldLabels = [
        'type' => 'Tipo',
        'source' => 'Fonte',
        'classification' => 'Classificação da saída',
        'recurrence_interval' => 'Intervalo de recorrência',
        'recurrence_duration' => 'Duração da recorrência',
        'recurrence_count' => 'Número de parcelas',
        'recurrence_months' => 'Meses de ocorrência',
        'amount' => 'Valor',
        'transaction_date' => 'Data',
        'description' => 'Descrição',
        'document_path' => 'Comprovante',
        'document_name' => 'Nome do comprovante',
        'user_id' => 'Responsável',
        'name' => 'Nome',
        'email' => 'E-mail',
        'is_main_admin' => 'Administrador principal',
        'is_active' => 'Conta ativa',
        'deleted_at' => 'Excluído em',
        'created_at' => 'Criado em',
    ];

    $format = function (mixed $value): string {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Sim' : 'Não';
        }

        if (is_array($value)) {
            return implode(', ', $value);
        }

        return (string) $value;
    };
@endphp

<div class="space-y-5">
    <x-page-header title="Logs do Sistema"
                   subtitle="Trilha de auditoria gravada automaticamente a cada inserção, alteração ou exclusão.">
        <x-slot:actions>
            <button type="button" class="btn-outline" wire:click="clearFilters">Limpar filtros</button>
        </x-slot:actions>
    </x-page-header>

    <x-period-filter :options="PeriodFilter::options()"
                     :current="$period"
                     :start-date="$startDate"
                     :end-date="$endDate"
                     :label="$this->periodLabel()" />

    <div class="card overflow-hidden">
        <div class="grid gap-3 border-b border-primary-100 p-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="search" class="label">Buscar</label>
                <input id="search" type="search" class="input" placeholder="Descrição ou usuário"
                       wire:model.live.debounce.400ms="search">
            </div>

            <div>
                <label for="actionFilter" class="label">Ação</label>
                <select id="actionFilter" class="input" wire:model.live="actionFilter">
                    <option value="">Todas</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action->value }}">{{ $action->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="resourceFilter" class="label">Recurso</label>
                <select id="resourceFilter" class="input" wire:model.live="resourceFilter">
                    <option value="">Todos</option>
                    @foreach ($resources as $class => $resourceLabel)
                        <option value="{{ $class }}">{{ $resourceLabel }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="userFilter" class="label">Usuário</label>
                <select id="userFilter" class="input" wire:model.live="userFilter">
                    <option value="">Todos</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if ($logs->isEmpty())
            <x-empty-state title="Nenhum registro no período"
                           description="Os logs são gerados automaticamente conforme o sistema é utilizado." />
        @else
            <ul class="divide-y divide-primary-50">
                @foreach ($logs as $log)
                    <li wire:key="log-{{ $log->id }}" class="px-4 py-3 hover:bg-primary-50/40">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="badge {{ $log->action->badgeClasses() }}">{{ $log->action->label() }}</span>
                                    <span class="badge bg-primary-50 text-primary-500">{{ $log->loggableLabel() }} #{{ $log->loggable_id }}</span>
                                </div>

                                <p class="mt-1.5 text-sm font-medium text-primary-800">{{ $log->description }}</p>

                                <p class="mt-0.5 text-xs text-primary-400">
                                    Por <span class="font-medium text-primary-500">{{ $log->user_name }}</span>
                                    em {{ $log->created_at->format('d/m/Y H:i:s') }}
                                    @if ($log->ip_address)
                                        · IP {{ $log->ip_address }}
                                    @endif
                                </p>
                            </div>

                            @if ($log->old_values || $log->new_values)
                                <button type="button" class="btn-ghost shrink-0 px-2 py-1 text-xs" wire:click="toggle({{ $log->id }})">
                                    {{ $expandedLog === $log->id ? 'Ocultar detalhes' : 'Ver detalhes' }}
                                </button>
                            @endif
                        </div>

                        @if ($expandedLog === $log->id)
                            <div class="mt-3 overflow-x-auto rounded-lg border border-primary-100">
                                <table class="min-w-full divide-y divide-primary-100 text-sm">
                                    <thead class="bg-primary-50/60">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-semibold text-primary-500 uppercase">Campo</th>
                                            <th class="px-3 py-2 text-left text-xs font-semibold text-primary-500 uppercase">Antes</th>
                                            <th class="px-3 py-2 text-left text-xs font-semibold text-primary-500 uppercase">Depois</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-primary-50">
                                        @foreach ($log->changes() as $field => $change)
                                            <tr>
                                                <td class="px-3 py-2 font-medium text-primary-700">
                                                    {{ $fieldLabels[$field] ?? $field }}
                                                </td>
                                                <td class="px-3 py-2 text-danger-700">{{ $format($change['old']) }}</td>
                                                <td class="px-3 py-2 text-secondary-700">{{ $format($change['new']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>

            <div class="border-t border-primary-100 px-4 py-3">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
