<div class="w-full max-w-sm">
    <div class="mb-6 flex flex-col items-center text-center">
        <img src="{{ asset('images/logo-assesg.png') }}" alt="ASSESG" class="h-40 w-40 object-contain">
        <h1 class="mt-3 text-lg font-bold text-primary-800">Controle de Caixa</h1>
        <p class="text-sm text-primary-400">Acesse com suas credenciais institucionais.</p>
    </div>

    <form wire:submit="login" class="card space-y-4 p-6">
        <div>
            <label for="email" class="label">E-mail</label>
            <input id="email" type="email" autocomplete="username" autofocus
                   class="input @error('email') input-error @enderror"
                   wire:model="email">
            @error('email') <p class="error-message">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="label">Senha</label>
            <input id="password" type="password" autocomplete="current-password"
                   class="input @error('password') input-error @enderror"
                   wire:model="password">
            @error('password') <p class="error-message">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-primary-600">
            <input type="checkbox" class="rounded border-primary-300 text-primary-500 focus:ring-primary-500/30"
                   wire:model="remember">
            Manter conectado
        </label>

        <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled" wire:target="login">
            <span wire:loading.remove wire:target="login">Entrar</span>
            <span wire:loading wire:target="login">Entrando…</span>
        </button>
    </form>

    <p class="mt-4 text-center text-xs text-primary-400">
        Novos acessos são criados pelo administrador principal.
    </p>
</div>
