@props(['title', 'subtitle' => null])

<div class="rounded-xl border border-primary-100 bg-white shadow-sm">
    <div class="border-b border-primary-50 px-4 py-3">
        <h3 class="text-sm font-bold text-primary-800">{{ $title }}</h3>
        @if ($subtitle)
            <p class="mt-0.5 text-xs text-primary-400">{{ $subtitle }}</p>
        @endif
    </div>

    <div class="p-3">
        {{ $slot }}
    </div>
</div>
