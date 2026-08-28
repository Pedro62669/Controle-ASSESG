@props(['title', 'description' => null])

<div class="flex flex-col items-center justify-center px-6 py-12 text-center">
    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-50 text-primary-300">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859m-19.5 0v5.625c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125V13.5m-19.5 0 2.7-7.425A2.25 2.25 0 0 1 7.05 4.5h9.9a2.25 2.25 0 0 1 2.1 1.575l2.7 7.425"/>
        </svg>
    </span>
    <h3 class="mt-3 text-sm font-semibold text-primary-700">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-primary-400">{{ $description }}</p>
    @endif
    {{ $slot }}
</div>
