@props(['options', 'current', 'startDate', 'endDate', 'label'])

{{-- Filtro global de período: reage via Livewire e alimenta todos os totalizadores. --}}
<div class="rounded-xl border border-primary-100 bg-white p-3 shadow-sm">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex items-center gap-2.5 px-1">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-accent-100 text-accent-800">
                <svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                </svg>
            </span>
            <div class="leading-tight">
                <p class="text-[11px] font-semibold tracking-wider text-primary-400 uppercase">Período analisado</p>
                <p class="text-sm font-bold text-primary-800">{{ $label }}</p>
            </div>
        </div>

        <div class="flex flex-col gap-2.5 sm:flex-row sm:items-center">
            <div class="flex flex-wrap gap-0.5 rounded-lg bg-primary-50 p-1">
                @foreach ($options as $value => $optionLabel)
                    <button type="button"
                            wire:click="selectPeriod('{{ $value }}')"
                            @class([
                                'rounded-md px-3 py-1.5 text-sm font-medium transition',
                                'bg-white text-primary-700 shadow-sm ring-1 ring-primary-100' => $current === $value,
                                'text-primary-500 hover:bg-white/60 hover:text-primary-700' => $current !== $value,
                            ])>
                        {{ $optionLabel }}
                    </button>
                @endforeach
            </div>

            @if ($current === 'custom')
                <div class="flex flex-wrap items-center gap-2 rounded-lg bg-primary-50 px-2.5 py-1.5">
                    <label for="period-start" class="text-xs font-semibold text-primary-500">De</label>
                    <input id="period-start" type="date" class="input w-auto py-1 text-sm" wire:model.live="startDate" value="{{ $startDate }}">

                    <label for="period-end" class="text-xs font-semibold text-primary-500">até</label>
                    <input id="period-end" type="date" class="input w-auto py-1 text-sm" wire:model.live="endDate" value="{{ $endDate }}">
                </div>
            @endif
        </div>
    </div>
</div>
