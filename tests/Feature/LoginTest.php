<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function usuario_ativo_entra_no_sistema(): void
    {
        $user = User::factory()->create([
            'email' => 'tesouraria@assesg.org.br',
            'password' => Hash::make('senha-valida-123'),
        ]);

        Livewire::test(Login::class)
            ->set('email', 'tesouraria@assesg.org.br')
            ->set('password', 'senha-valida-123')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function senha_incorreta_e_recusada(): void
    {
        User::factory()->create([
            'email' => 'tesouraria@assesg.org.br',
            'password' => Hash::make('senha-valida-123'),
        ]);

        Livewire::test(Login::class)
            ->set('email', 'tesouraria@assesg.org.br')
            ->set('password', 'senha-errada')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function conta_inativa_nao_entra_mesmo_com_a_senha_correta(): void
    {
        User::factory()->inactive()->create([
            'email' => 'desligado@assesg.org.br',
            'password' => Hash::make('senha-valida-123'),
        ]);

        Livewire::test(Login::class)
            ->set('email', 'desligado@assesg.org.br')
            ->set('password', 'senha-valida-123')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }
}
