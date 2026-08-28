@php
    use App\Enums\PeriodFilter;

    $summary = $this->summary;
    $money = fn (float $value): string => 'R$ '.number_format($value, 2, ',', '.');
@endphp

<div class="space-y-5">
    <x-page-header title="Fluxo de Caixa" subtitle="Acompanhamento de entradas, saídas e saldo retido.">
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

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Saldo em caixa"
                     :value="$money($summary['balance'])"
                     featured
                     hint="Acumulado até o fim do período"
                     icon="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />

        <x-stat-card label="Entradas no período"
                     :value="$money($summary['income'])"
                     tone="secondary"
                     :hint="$this->periodLabel()"
                     icon="M12 19.5v-15m0 0-6.75 6.75M12 4.5l6.75 6.75" />

        <x-stat-card label="Saídas no período"
                     :value="$money($summary['expense'])"
                     tone="danger"
                     :hint="$this->periodLabel()"
                     icon="M12 4.5v15m0 0 6.75-6.75M12 19.5l-6.75-6.75" />

        <x-stat-card label="Resultado do período"
                     :value="$money($summary['result'])"
                     :tone="$summary['result'] >= 0 ? 'secondary' : 'danger'"
                     :hint="$summary['count'].' '.\Illuminate\Support\Str::plural('movimentação', $summary['count'])"
                     icon="M3 13.5 8.25 8.25l3.75 3.75 6.75-6.75M21 3.75h-4.5M21 3.75V8.25" />
    </div>

    {{--
        O ApexCharts controla o próprio DOM: o bloco fica sob wire:ignore e os
        dados chegam pelo evento charts-updated disparado a cada troca de filtro.
    --}}
    <div wire:ignore x-data="dashboardCharts(@js($this->charts))" class="space-y-4">
        <x-chart-card title="Comparativo de fluxo de caixa"
                      subtitle="Entradas e saídas ao longo do período selecionado">
            <div x-ref="cashFlow" class="min-h-[340px]"></div>
        </x-chart-card>

        <div class="grid gap-4 lg:grid-cols-3">
            <x-chart-card title="Valor total retido"
                          subtitle="Quanto do que entrou permanece em caixa">
                <div x-ref="retained" class="min-h-[400px]"></div>
            </x-chart-card>

            <x-chart-card title="Total de entradas"
                          subtitle="Composição por origem do recurso">
                <div x-ref="income" class="min-h-[400px]"></div>
            </x-chart-card>

            <x-chart-card title="Total de saídas"
                          subtitle="Composição por destino do recurso">
                <div x-ref="expense" class="min-h-[400px]"></div>
            </x-chart-card>
        </div>
    </div>

    @php
        $projection = $this->projection;
    @endphp

    <div class="card overflow-hidden">
        <div class="card-header flex-wrap">
            <div>
                <h3 class="text-sm font-bold text-primary-800">
                    Projeção dos próximos {{ $projection['months'] }} meses
                </h3>
                <p class="mt-0.5 text-xs text-primary-400">
                    {{ $projection['series'] === 1
                        ? 'Calculada a partir de 1 movimentação recorrente já lançada'
                        : 'Calculada a partir de '.$projection['series'].' movimentações recorrentes já lançadas' }}
                    — nada é gravado no caixa.
                </p>
            </div>

            <div class="flex flex-wrap gap-0.5 rounded-lg bg-primary-50 p-1">
                @foreach ($horizons as $horizon)
                    <button type="button"
                            wire:click="selectProjectionHorizon({{ $horizon }})"
                            @class([
                                'rounded-md px-3 py-1.5 text-sm font-medium transition',
                                'bg-white text-primary-700 shadow-sm ring-1 ring-primary-100' => $projectionMonths === $horizon,
                                'text-primary-500 hover:bg-white/60 hover:text-primary-700' => $projectionMonths !== $horizon,
                            ])>
                        {{ $horizon }} meses
                    </button>
                @endforeach
            </div>
        </div>

        @if ($projection['series'] === 0)
            <x-empty-state title="Nenhuma movimentação recorrente lançada"
                           description="Marque uma entrada ou saída como recorrente para que ela apareça na projeção." />
        @else
            <div class="grid gap-4 border-b border-primary-50 p-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-stat-card label="Entradas previstas"
                             :value="$money($projection['totals']['income'])"
                             tone="secondary"
                             :hint="'Próximos '.$projection['months'].' meses'" />

                <x-stat-card label="Saídas previstas"
                             :value="$money($projection['totals']['expense'])"
                             tone="danger"
                             :hint="'Próximos '.$projection['months'].' meses'" />

                <x-stat-card label="Resultado previsto"
                             :value="$money($projection['totals']['result'])"
                             :tone="$projection['totals']['result'] >= 0 ? 'secondary' : 'danger'"
                             hint="Entradas menos saídas projetadas" />

                <x-stat-card label="Saldo ao fim do período"
                             :value="$money($projection['totals']['finalBalance'])"
                             :tone="$projection['totals']['finalBalance'] >= 0 ? 'primary' : 'danger'"
                             hint="Caixa de hoje mais a projeção" />
            </div>

            {{-- Só o gráfico fica sob wire:ignore: o cabeçalho e os
                 totalizadores acima precisam continuar reativos. --}}
            <div wire:ignore x-data="dashboardChart('projection', @js($projection))" class="p-3">
                <div x-ref="chart" class="min-h-[360px]"></div>
            </div>
        @endif
    </div>

    <div class="card overflow-hidden">
        <div class="card-header">
            <h3 class="text-sm font-bold text-primary-800">Últimas movimentações do período</h3>
            <a href="{{ route('transactions.index', ['periodo' => $period, 'inicio' => $startDate, 'fim' => $endDate]) }}"
               wire:navigate class="text-sm font-medium text-primary-500 hover:text-primary-700">
                Ver todas
            </a>
        </div>

        @if ($this->latestTransactions->isEmpty())
            <x-empty-state title="Nenhuma movimentação no período"
                           description="Ajuste o filtro acima ou registre a primeira entrada ou saída." />
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-primary-100">
                    <thead class="bg-primary-50/60">
                        <tr>
                            <th class="table-head">Data</th>
                            <th class="table-head">Tipo</th>
                            <th class="table-head">Descrição</th>
                            <th class="table-head">Responsável</th>
                            <th class="table-head text-right">Valor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-primary-50">
                        @foreach ($this->latestTransactions as $transaction)
                            <tr class="hover:bg-primary-50/40">
                                <td class="table-cell whitespace-nowrap">{{ $transaction->transaction_date->format('d/m/Y') }}</td>
                                <td class="table-cell">
                                    <span @class([
                                        'badge',
                                        'bg-secondary-100 text-secondary-800' => $transaction->type === \App\Enums\TransactionType::Income,
                                        'bg-danger-100 text-danger-800' => $transaction->type === \App\Enums\TransactionType::Expense,
                                    ])>{{ $transaction->type->label() }}</span>
                                </td>
                                <td class="table-cell max-w-md">
                                    <span class="line-clamp-1">
                                        {{ $transaction->description ?? $transaction->document_name ?? '—' }}
                                    </span>
                                </td>
                                <td class="table-cell whitespace-nowrap">{{ $transaction->user?->name ?? '—' }}</td>
                                <td @class([
                                    'table-amount',
                                    'text-secondary-700' => $transaction->type === \App\Enums\TransactionType::Income,
                                    'text-danger-700' => $transaction->type === \App\Enums\TransactionType::Expense,
                                ])>
                                    {{ $transaction->type === \App\Enums\TransactionType::Income ? '+' : '−' }}
                                    {{ $transaction->formatted_amount }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <livewire:transactions.transaction-editor />
</div>
