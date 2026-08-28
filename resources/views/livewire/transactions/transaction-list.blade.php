@php
    use App\Enums\PeriodFilter;
    use App\Enums\TransactionType;

    $summary = $this->summary;
    $money = fn (float $value): string => 'R$ '.number_format($value, 2, ',', '.');
    $currentUser = auth()->user();
@endphp

<div class="space-y-5">
    <x-page-header title="Movimentações" subtitle="Entradas e saídas registradas no período.">
        <x-slot:actions>
            <button type="button" class="btn-primary" wire:click="$dispatch('open-transaction-editor')">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                Nova movimentação
            </button>
        </x-slot:actions>
    </x-page-header>

    <x-period-filter :options="PeriodFilter::options()"
                     :current="$period"
                     :start-date="$startDate"
                     :end-date="$endDate"
                     :label="$this->periodLabel()" />

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card label="Entradas" :value="$money($summary['income'])" tone="secondary" />
        <x-stat-card label="Saídas" :value="$money($summary['expense'])" tone="danger" />
        <x-stat-card label="Resultado" :value="$money($summary['result'])"
                     :tone="$summary['result'] >= 0 ? 'secondary' : 'danger'" />
    </div>

    <div class="card overflow-hidden">
        <div class="grid gap-3 border-b border-primary-100 p-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <label for="search" class="label">Buscar</label>
                <input id="search" type="search" class="input" placeholder="Descrição, comprovante ou responsável"
                       wire:model.live.debounce.400ms="search">
            </div>

            <div>
                <label for="typeFilter" class="label">Tipo</label>
                <select id="typeFilter" class="input" wire:model.live="typeFilter">
                    <option value="">Todos</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="documentFilter" class="label">Comprovante</label>
                <select id="documentFilter" class="input" wire:model.live="documentFilter">
                    <option value="">Todos</option>
                    <option value="with">Com anexo</option>
                    <option value="without">Sem anexo (justificadas)</option>
                </select>
            </div>
        </div>

        @if ($transactions->isEmpty())
            <x-empty-state title="Nenhuma movimentação encontrada"
                           description="Revise os filtros ou registre uma nova entrada ou saída." />
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-primary-100">
                    <thead class="bg-primary-50/60">
                        <tr>
                            <th class="table-head">Data</th>
                            <th class="table-head">Tipo</th>
                            <th class="table-head">Descrição / Comprovante</th>
                            <th class="table-head">Responsável</th>
                            <th class="table-head text-right">Valor</th>
                            <th class="table-head text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-primary-50">
                        @foreach ($transactions as $transaction)
                            @php
                                $canManage = $currentUser?->isMainAdmin() || $transaction->user_id === $currentUser?->id;
                            @endphp

                            <tr wire:key="transaction-{{ $transaction->id }}" class="hover:bg-primary-50/40">
                                <td class="table-cell whitespace-nowrap">{{ $transaction->transaction_date->format('d/m/Y') }}</td>

                                <td class="table-cell">
                                    <span @class([
                                        'badge',
                                        'bg-secondary-100 text-secondary-800' => $transaction->type === TransactionType::Income,
                                        'bg-danger-100 text-danger-800' => $transaction->type === TransactionType::Expense,
                                    ])>{{ $transaction->type->label() }}</span>

                                    @if ($transaction->classification)
                                        <span class="badge mt-1 {{ $transaction->classification->badgeClasses() }}">
                                            {{ $transaction->classification->label() }}
                                        </span>
                                    @endif
                                </td>

                                <td class="table-cell max-w-sm">
                                    @if ($transaction->source)
                                        <p class="font-medium text-primary-800">{{ $transaction->source->label() }}</p>
                                    @endif

                                    @if ($transaction->description)
                                        <p class="line-clamp-2 text-primary-500">{{ $transaction->description }}</p>
                                    @endif

                                    @if ($transaction->recurrenceSummary())
                                        <p class="mt-0.5 flex items-center gap-1 text-xs text-primary-400">
                                            <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                      d="M16.023 9.348h4.992V4.356m-.001 9.667A8.25 8.25 0 1 1 5.633 6.364m14.381 7.659h-4.99"/>
                                            </svg>
                                            {{ $transaction->recurrenceSummary() }}
                                        </p>
                                    @endif

                                    @if ($transaction->has_document)
                                        <a href="{{ $transaction->documentUrl() }}" target="_blank" rel="noopener"
                                           class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-primary-500 hover:text-primary-700">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                      d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13"/>
                                            </svg>
                                            {{ $transaction->document_name ?? 'Comprovante' }}
                                        </a>
                                    @elseif (! $transaction->description)
                                        <span class="text-primary-300">—</span>
                                    @endif
                                </td>

                                <td class="table-cell whitespace-nowrap">{{ $transaction->user?->name ?? '—' }}</td>

                                <td @class([
                                    'table-amount',
                                    'text-secondary-700' => $transaction->type === TransactionType::Income,
                                    'text-danger-700' => $transaction->type === TransactionType::Expense,
                                ])>
                                    {{ $transaction->type === TransactionType::Income ? '+' : '−' }}
                                    {{ $transaction->formatted_amount }}
                                </td>

                                <td class="table-cell text-right whitespace-nowrap">
                                    @if ($canManage)
                                        @if ($confirmingDeletionOf === $transaction->id)
                                            <div class="flex items-center justify-end gap-2">
                                                <span class="text-xs text-primary-500">Excluir?</span>
                                                <button type="button" class="text-xs font-semibold text-danger-600 hover:text-danger-800"
                                                        wire:click="delete({{ $transaction->id }})">Sim</button>
                                                <button type="button" class="text-xs font-semibold text-primary-400 hover:text-primary-600"
                                                        wire:click="cancelDeletion">Não</button>
                                            </div>
                                        @else
                                            <div class="flex items-center justify-end gap-1">
                                                <button type="button" class="btn-ghost p-1.5" title="Editar"
                                                        wire:click="$dispatch('open-transaction-editor', { transactionId: {{ $transaction->id }} })">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                              d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/>
                                                    </svg>
                                                    <span class="sr-only">Editar</span>
                                                </button>

                                                <button type="button" class="btn-ghost p-1.5 text-danger-500 hover:bg-danger-50" title="Excluir"
                                                        wire:click="confirmDeletion({{ $transaction->id }})">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                              d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                                    </svg>
                                                    <span class="sr-only">Excluir</span>
                                                </button>
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-xs text-primary-300">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-primary-100 px-4 py-3">
                {{ $transactions->links() }}
            </div>
        @endif
    </div>

    <livewire:transactions.transaction-editor />
</div>
