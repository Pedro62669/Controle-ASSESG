<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LogAction;
use App\Models\SystemLog;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cobre a gravação automática de logs pelos observers.
 */
class SystemLogObserverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function criacao_de_transacao_gera_log_com_o_usuario_responsavel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $transaction = Transaction::factory()->for($user)->income()->create(['amount' => 250.00]);

        $log = SystemLog::query()
            ->where('loggable_type', Transaction::class)
            ->where('loggable_id', $transaction->getKey())
            ->sole();

        $this->assertSame(LogAction::Created, $log->action);
        $this->assertSame($user->getKey(), $log->user_id);
        $this->assertSame($user->name, $log->user_name);
        $this->assertNotNull($log->new_values);
    }

    #[Test]
    public function alteracao_registra_valores_antes_e_depois(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $transaction = Transaction::factory()->for($user)->create(['amount' => 100.00]);

        SystemLog::query()->delete();

        $transaction->update(['amount' => 175.50]);

        $log = SystemLog::query()->latest('id')->sole();

        $this->assertSame(LogAction::Updated, $log->action);
        $this->assertSame(100.0, (float) $log->old_values['amount']);
        $this->assertSame(175.5, (float) $log->new_values['amount']);
    }

    #[Test]
    public function alteracao_sem_mudanca_real_nao_gera_log(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $transaction = Transaction::factory()->for($user)->create(['amount' => 100.00]);

        SystemLog::query()->delete();

        $transaction->update(['amount' => 100.00]);

        $this->assertSame(0, SystemLog::query()->count());
    }

    #[Test]
    public function exclusao_gera_log_com_os_valores_anteriores(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $transaction = Transaction::factory()->for($user)->create();

        SystemLog::query()->delete();

        $transaction->delete();

        $log = SystemLog::query()->latest('id')->sole();

        $this->assertSame(LogAction::Deleted, $log->action);
        $this->assertNotNull($log->old_values);
    }

    #[Test]
    public function log_de_usuario_nunca_grava_a_senha(): void
    {
        $admin = User::factory()->mainAdmin()->create();
        $this->actingAs($admin);

        SystemLog::query()->delete();

        $created = User::factory()->create();

        $log = SystemLog::query()
            ->where('loggable_type', User::class)
            ->where('loggable_id', $created->getKey())
            ->sole();

        $this->assertArrayNotHasKey('password', $log->new_values ?? []);
        $this->assertArrayNotHasKey('remember_token', $log->new_values ?? []);
    }
}
