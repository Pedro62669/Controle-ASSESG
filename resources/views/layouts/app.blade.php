<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ asset('images/logo-assesg.png') }}" type="image/png">

    <title>{{ $title ?? 'Controle de Caixa' }} — ASSESG</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full font-sans antialiased">
    @php
        $user = auth()->user();
        $links = [
            ['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'chart'],
            ['route' => 'transactions.index', 'label' => 'Movimentações', 'icon' => 'list'],
        ];

        if ($user?->isMainAdmin()) {
            $links[] = ['route' => 'admin.users', 'label' => 'Usuários', 'icon' => 'users'];
            $links[] = ['route' => 'admin.logs', 'label' => 'Logs', 'icon' => 'shield'];
        }
    @endphp

    <div x-data="{ mobileMenu: false }" class="min-h-full">
        {{-- Fixa no topo: o filtro de período e os totais continuam a um clique
             mesmo depois de rolar listagens longas. --}}
        <header class="sticky top-0 z-30 border-b border-primary-100 bg-white/95 shadow-sm backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-2.5 sm:px-6 lg:px-8">
                <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3">
                    <img src="{{ asset('images/logo-assesg.png') }}" alt="ASSESG" class="h-14 w-14 object-contain">
                    <span class="hidden text-base font-bold tracking-wide text-primary-500 sm:block">
                        Controle de Caixa
                    </span>
                </a>

                <nav class="hidden items-center gap-1 md:flex">
                    @foreach ($links as $link)
                        <x-nav-link :route="$link['route']" :icon="$link['icon']">{{ $link['label'] }}</x-nav-link>
                    @endforeach
                </nav>

                <div class="flex items-center gap-2">
                    @php
                        $initials = collect(explode(' ', trim((string) $user?->name)))
                            ->filter()
                            ->take(2)
                            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
                            ->implode('');
                    @endphp

                    <div class="hidden items-center gap-2.5 sm:flex">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-500 text-xs font-bold text-white">
                            {{ $initials }}
                        </span>
                        <div class="text-right leading-tight">
                            <p class="text-sm font-semibold text-primary-800">{{ $user?->name }}</p>
                            <p class="text-xs text-primary-400">
                                {{ $user?->isMainAdmin() ? 'Administrador principal' : 'Usuário' }}
                            </p>
                        </div>
                    </div>

                    <span class="hidden h-6 w-px bg-primary-100 sm:block"></span>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn-ghost" title="Sair">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M18 15l3-3m0 0-3-3m3 3H9"/>
                            </svg>
                            <span class="sr-only">Sair</span>
                        </button>
                    </form>

                    <button type="button" @click="mobileMenu = !mobileMenu" class="btn-ghost md:hidden" aria-label="Abrir menu">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/>
                        </svg>
                    </button>
                </div>
            </div>

            <nav x-show="mobileMenu" x-cloak x-collapse class="border-t border-primary-100 px-4 py-2 md:hidden">
                <div class="flex flex-col gap-1">
                    @foreach ($links as $link)
                        <x-nav-link :route="$link['route']" :icon="$link['icon']">{{ $link['label'] }}</x-nav-link>
                    @endforeach
                </div>
            </nav>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            {{ $slot }}
        </main>

        <x-toast />
    </div>
</body>
</html>
