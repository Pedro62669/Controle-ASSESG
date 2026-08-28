@props(['title', 'subtitle' => null])

<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex items-center gap-3">
        <span class="h-9 w-1 shrink-0 rounded-full bg-secondary-500"></span>
        <div>
            <h1 class="text-xl font-bold tracking-tight text-primary-800">{{ $title }}</h1>
            @if ($subtitle)
                <p class="text-sm text-primary-400">{{ $subtitle }}</p>
            @endif
        </div>
    </div>

    {{ $actions ?? '' }}
</div>
