<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cobre o isolamento das áreas exclusivas do administrador principal.
 */
class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function visitante_e_redirecionado_para_o_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    #[Test]
    public function usuario_comum_acessa_dashboard_e_movimentacoes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('transactions.index'))->assertOk();
    }

    #[Test]
    public function usuario_comum_nao_acessa_cadastro_de_usuarios_nem_logs(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.users'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.logs'))->assertForbidden();
    }

    #[Test]
    public function administrador_principal_acessa_as_areas_exclusivas(): void
    {
        $admin = User::factory()->mainAdmin()->create();

        $this->actingAs($admin)->get(route('admin.users'))->assertOk();
        $this->actingAs($admin)->get(route('admin.logs'))->assertOk();
    }

    #[Test]
    public function conta_inativa_e_deslogada_na_requisicao_seguinte(): void
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
