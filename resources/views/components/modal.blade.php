@props(['title', 'subtitle' => null, 'maxWidth' => 'max-w-2xl'])

<div class="fixed inset-0 z-40 overflow-y-auto" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-primary-950/40" {{ $attributes->only('wire:click') }}></div>

    <div class="flex min-h-full items-start justify-center p-4 sm:items-center">
        <div class="relative w-full {{ $maxWidth }} rounded-xl bg-white shadow-xl">
            <div class="flex items-start justify-between gap-4 border-b border-primary-100 px-5 py-4">
                <div>
                    <h2 class="text-base font-bold text-primary-800">{{ $title }}</h2>
                    @if ($subtitle)
                        <p class="mt-0.5 text-xs text-primary-400">{{ $subtitle }}</p>
                    @endif
                </div>

                {{ $close ?? '' }}
            </div>

            {{ $slot }}
        </div>
    </div>
</div>
