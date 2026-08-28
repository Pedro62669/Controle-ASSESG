<div class="space-y-5">
    <x-page-header title="Usuários" subtitle="Área exclusiva do administrador principal.">
        <x-slot:actions>
            <button type="button" class="btn-primary" wire:click="create">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                Novo usuário
            </button>
        </x-slot:actions>
    </x-page-header>

    <div class="card overflow-hidden">
        <div class="border-b border-primary-100 p-4">
            <label for="search" class="label">Buscar</label>
            <input id="search" type="search" class="input max-w-md" placeholder="Nome ou e-mail"
                   wire:model.live.debounce.400ms="search">
        </div>

        @if ($users->isEmpty())
            <x-empty-state title="Nenhum usuário encontrado" description="Ajuste a busca ou cadastre um novo acesso." />
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-primary-100">
                    <thead class="bg-primary-50/60">
                        <tr>
                            <th class="table-head">Nome</th>
                            <th class="table-head">E-mail</th>
                            <th class="table-head">Perfil</th>
                            <th class="table-head">Situação</th>
                            <th class="table-head text-right">Movimentações</th>
                            <th class="table-head text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-primary-50">
                        @foreach ($users as $user)
                            <tr wire:key="user-{{ $user->id }}" class="hover:bg-primary-50/40">
                                <td class="table-cell font-medium">{{ $user->name }}</td>
                                <td class="table-cell">{{ $user->email }}</td>
                                <td class="table-cell">
                                    <span @class([
                                        'badge',
                                        'bg-primary-100 text-primary-800' => $user->is_main_admin,
                                        'bg-primary-50 text-primary-500' => ! $user->is_main_admin,
                                    ])>
                                        {{ $user->is_main_admin ? 'Administrador principal' : 'Usuário' }}
                                    </span>
                                </td>
                                <td class="table-cell">
                                    <span @class([
                                        'badge',
                                        'bg-secondary-100 text-secondary-800' => $user->is_active,
                                        'bg-danger-100 text-danger-800' => ! $user->is_active,
                                    ])>
                                        {{ $user->is_active ? 'Ativo' : 'Inativo' }}
                                    </span>
                                </td>
                                <td class="table-cell text-right tabular-nums">{{ $user->transactions_count }}</td>
                                <td class="table-cell text-right whitespace-nowrap">
                                    @if ($confirmingDeletionOf === $user->id)
                                        <div class="flex items-center justify-end gap-2">
                                            <span class="text-xs text-primary-500">Remover?</span>
                                            <button type="button" class="text-xs font-semibold text-danger-600 hover:text-danger-800"
                                                    wire:click="delete({{ $user->id }})">Sim</button>
                                            <button type="button" class="text-xs font-semibold text-primary-400 hover:text-primary-600"
                                                    wire:click="cancelDeletion">Não</button>
                                        </div>
                                    @else
                                        <div class="flex items-center justify-end gap-1">
                                            <button type="button" class="btn-ghost p-1.5" title="Editar" wire:click="edit({{ $user->id }})">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/>
                                                </svg>
                                                <span class="sr-only">Editar</span>
                                            </button>

                                            @if ($user->id !== auth()->id())
                                                <button type="button" class="btn-ghost p-1.5 text-danger-500 hover:bg-danger-50"
                                                        title="Remover" wire:click="confirmDeletion({{ $user->id }})">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                              d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166M18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                                    </svg>
                                                    <span class="sr-only">Remover</span>
                                                </button>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-primary-100 px-4 py-3">
                {{ $users->links() }}
            </div>
        @endif
    </div>

    @if ($open)
        <x-modal :title="$userId ? 'Editar usuário' : 'Novo usuário'"
                 subtitle="Somente o administrador principal acessa usuários e logs."
                 max-width="max-w-lg"
                 wire:click="close">
            <x-slot:close>
                <button type="button" class="btn-ghost -mt-1 -mr-2 p-2" wire:click="close" aria-label="Fechar">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </x-slot:close>

            <form wire:submit="save" class="space-y-4 px-5 py-5">
                <div>
                    <label for="name" class="label">Nome <span class="text-danger-600">*</span></label>
                    <input id="name" type="text" class="input @error('name') input-error @enderror" wire:model="name">
                    @error('name') <p class="error-message">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="user-email" class="label">E-mail <span class="text-danger-600">*</span></label>
                    <input id="user-email" type="email" class="input @error('email') input-error @enderror" wire:model="email">
                    @error('email') <p class="error-message">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="password" class="label">
                            Senha @unless ($userId) <span class="text-danger-600">*</span> @endunless
                        </label>
                        <input id="password" type="password" autocomplete="new-password"
                               class="input @error('password') input-error @enderror" wire:model="password">
                        @error('password') <p class="error-message">{{ $message }}</p> @enderror
                        @if ($userId)
                            <p class="mt-1 text-xs text-primary-400">Deixe em branco para manter a senha atual.</p>
                        @endif
                    </div>

                    <div>
                        <label for="password_confirmation" class="label">Confirmar senha</label>
                        <input id="password_confirmation" type="password" autocomplete="new-password"
                               class="input" wire:model="password_confirmation">
                    </div>
                </div>

                <div class="space-y-2 rounded-lg bg-primary-50 p-3">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" class="mt-0.5 rounded border-primary-300 text-primary-500 focus:ring-primary-500/30"
                               wire:model="is_main_admin">
                        <span>
                            <span class="block text-sm font-medium text-primary-800">Administrador principal</span>
                            <span class="block text-xs text-primary-400">Concede acesso ao cadastro de usuários e aos logs do sistema.</span>
                        </span>
                    </label>
                    @error('is_main_admin') <p class="error-message">{{ $message }}</p> @enderror

                    <label class="flex items-start gap-3">
                        <input type="checkbox" class="mt-0.5 rounded border-primary-300 text-primary-500 focus:ring-primary-500/30"
                               wire:model="is_active">
                        <span>
                            <span class="block text-sm font-medium text-primary-800">Conta ativa</span>
                            <span class="block text-xs text-primary-400">Contas inativas não conseguem entrar no sistema.</span>
                        </span>
                    </label>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-primary-100 pt-4 sm:flex-row sm:justify-end">
                    <button type="button" class="btn-outline" wire:click="close">Cancelar</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save">
                        {{ $userId ? 'Salvar alterações' : 'Cadastrar usuário' }}
                    </button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
