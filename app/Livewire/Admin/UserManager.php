<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\User;
use App\Validation\UserValidation;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Cadastro de usuários — área exclusiva do administrador principal
 * (protegida pelo middleware main.admin nas rotas).
 */
#[Title('Usuários')]
class UserManager extends Component
{
    use WithPagination;

    #[Url(as: 'busca')]
    public string $search = '';

    public bool $open = false;

    public ?int $userId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $is_main_admin = false;

    public bool $is_active = true;

    public ?int $confirmingDeletionOf = null;

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return UserValidation::rules($this->editingUser());
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return UserValidation::messages();
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return UserValidation::attributes();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->open = true;
    }

    public function edit(int $userId): void
    {
        $this->resetForm();

        $user = User::query()->findOrFail($userId);

        $this->userId = $user->getKey();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->is_main_admin = $user->is_main_admin;
        $this->is_active = $user->is_active;

        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->resetForm();
    }

    public function save(): void
    {
        $validated = $this->validate();

        $attributes = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_main_admin' => $this->is_main_admin,
            'is_active' => $this->is_active,
        ];

        // Na edição, senha em branco significa "manter a atual".
        if (filled($validated['password'] ?? null)) {
            $attributes['password'] = $validated['password'];
        }

        $user = $this->editingUser();

        if ($user === null) {
            User::query()->create($attributes);
        } else {
            $this->guardAgainstSelfLockout($user);

            $user->update($attributes);
        }

        $this->open = false;
        $this->resetForm();
        $this->resetPage();

        $this->dispatch('notify', type: 'success', message: 'Usuário salvo com sucesso.');
    }

    public function confirmDeletion(int $userId): void
    {
        $this->confirmingDeletionOf = $userId;
    }

    public function cancelDeletion(): void
    {
        $this->confirmingDeletionOf = null;
    }

    public function delete(int $userId): void
    {
        $user = User::query()->findOrFail($userId);

        abort_if(
            $user->getKey() === Auth::id(),
            403,
            'Você não pode remover a própria conta.',
        );

        $user->delete();

        $this->confirmingDeletionOf = null;

        $this->dispatch('notify', type: 'success', message: 'Usuário removido.');
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->withCount('transactions')
            ->when($this->search !== '', function (Builder $query): void {
                $term = trim($this->search);

                $query->where(function (Builder $query) use ($term): void {
                    $query->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('is_main_admin')
            ->orderBy('name')
            ->paginate(12);
    }

    public function render(): View
    {
        return view('livewire.admin.user-manager', [
            'users' => $this->users(),
        ]);
    }

    private function editingUser(): ?User
    {
        return $this->userId === null
            ? null
            : User::query()->find($this->userId);
    }

    /**
     * Impede que o administrador principal remova o próprio acesso e deixe o
     * sistema sem ninguém para gerenciar usuários e logs.
     */
    private function guardAgainstSelfLockout(User $user): void
    {
        if ($user->getKey() !== Auth::id()) {
            return;
        }

        if ($this->is_main_admin && $this->is_active) {
            return;
        }

        $this->is_main_admin = true;
        $this->is_active = true;

        throw ValidationException::withMessages([
            'is_main_admin' => 'Você não pode remover o próprio acesso de administrador principal.',
        ]);
    }

    private function resetForm(): void
    {
        $this->reset(['userId', 'name', 'email', 'password', 'password_confirmation', 'is_main_admin', 'is_active']);
        $this->resetErrorBag();
        $this->is_active = true;
    }
}
