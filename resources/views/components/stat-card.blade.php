@props([
    'label',
    'value',
    'tone' => 'primary',
    'hint' => null,
    'icon' => null,
    'featured' => false,
])

@php
    // Cada tom recebe uma faixa superior própria: a cor identifica o indicador
    // antes mesmo da leitura do rótulo.
    $tones = [
        'primary' => ['bar' => 'bg-primary-500', 'chip' => 'bg-primary-50 text-primary-600', 'value' => 'text-primary-900'],
        'secondary' => ['bar' => 'bg-secondary-500', 'chip' => 'bg-secondary-50 text-secondary-700', 'value' => 'text-secondary-700'],
        'accent' => ['bar' => 'bg-accent-500', 'chip' => 'bg-accent-100 text-accent-800', 'value' => 'text-accent-900'],
        'danger' => ['bar' => 'bg-danger-500', 'chip' => 'bg-danger-50 text-danger-700', 'value' => 'text-danger-700'],
    ];

    $palette = $tones[$tone] ?? $tones['primary'];
@endphp

@if ($featured)
    {{-- O saldo é o número que governa a leitura da tela: ganha peso próprio. --}}
    <div class="relative overflow-hidden rounded-xl bg-primary-500 p-5 shadow-sm ring-1 ring-primary-600/20">
        <div class="pointer-events-none absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/5"></div>
        <div class="pointer-events-none absolute -bottom-16 -right-4 h-32 w-32 rounded-full bg-secondary-500/15"></div>

        <div class="relative flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs font-semibold tracking-wider text-primary-200/90 uppercase">{{ $label }}</p>
                <p class="mt-2 truncate text-3xl font-bold tabular-nums text-white" title="{{ $value }}">{{ $value }}</p>
                @if ($hint)
                    <p class="mt-1.5 text-xs text-primary-200/80">{{ $hint }}</p>
                @endif
            </div>

            @if ($icon)
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white/10 text-white">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/>
                    </svg>
                </span>
            @endif
        </div>
    </div>
@else
    <div class="group relative overflow-hidden rounded-xl border border-primary-100 bg-white p-5 shadow-sm transition hover:shadow-md">
        <span class="absolute inset-x-0 top-0 h-1 {{ $palette['bar'] }}"></span>

        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs font-semibold tracking-wider text-primary-400 uppercase">{{ $label }}</p>
                <p class="mt-2 truncate text-2xl font-bold tabular-nums {{ $palette['value'] }}" title="{{ $value }}">
                    {{ $value }}
                </p>
                @if ($hint)
                    <p class="mt-1.5 text-xs text-primary-400">{{ $hint }}</p>
                @endif
            </div>

            @if ($icon)
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $palette['chip'] }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/>
                    </svg>
                </span>
            @endif
        </div>
    </div>
@endif
