<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CashFlowReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CashFlowReportTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CashFlowReportService
    {
        return app(CashFlowReportService::class);
    }

    #[Test]
    public function totais_do_periodo_ignoram_movimentacoes_de_fora(): void
    {
        $user = User::factory()->create();

        Transaction::factory()->for($user)->income()->create([
            'amount' => 1000,
            'transaction_date' => '2026-05-10',
        ]);
        Transaction::factory()->for($user)->expense()->create([
            'amount' => 400,
            'transaction_date' => '2026-05-20',
        ]);
        Transaction::factory()->for($user)->income()->create([
            'amount' => 999,
            'transaction_date' => '2026-06-01',
        ]);

        $summary = $this->service()->summary(
            Carbon::parse('2026-05-01'),
            Carbon::parse('2026-05-31'),
        );

        $this->assertSame(1000.0, $summary['income']);
        $this->assertSame(400.0, $summary['expense']);
        $this->assertSame(600.0, $summary['result']);
        $this->assertSame(2, $summary['count']);
    }

    #[Test]
    public function saldo_em_caixa_acumula_todo_o_historico_ate_a_data_final(): void
    {
        $user = User::factory()->create();

        Transaction::factory()->for($user)->income()->create([
            'amount' => 800,
            'transaction_date' => '2026-01-15',
        ]);
        Transaction::factory()->for($user)->expense()->create([
            'amount' => 300,
            'transaction_date' => '2026-05-10',
        ]);

        $summary = $this->service()->summary(
            Carbon::parse('2026-05-01'),
            Carbon::parse('2026-05-31'),
        );

        // Entradas do período: 0 · Saídas: 300 · Saldo acumulado: 800 - 300
        $this->assertSame(0.0, $summary['income']);
        $this->assertSame(500.0, $summary['balance']);
    }

    #[Test]
    public function serie_temporal_preenche_todos_os_intervalos_do_periodo(): void
    {
        $user = User::factory()->create();

        Transaction::factory()->for($user)->income()->create([
            'amount' => 150,
            'transaction_date' => '2026-05-03',
        ]);

        $series = $this->service()->timeSeries(
            Carbon::parse('2026-05-01'),
            Carbon::parse('2026-05-07'),
        );

        $this->assertSame('day', $series['granularity']);
        $this->assertCount(7, $series['labels']);
        $this->assertSame(150.0, $series['income'][2]);
        $this->assertSame(0.0, $series['income'][0]);
    }

    #[Test]
    public function composicao_de_entradas_agrupa_por_fonte(): void
    {
        $user = User::factory()->create();

        Transaction::factory()->for($user)->income()->create([
            'source' => TransactionSource::PublicAgreement,
            'amount' => 700,
            'transaction_date' => '2026-05-05',
        ]);
        Transaction::factory()->for($user)->income()->create([
            'source' => TransactionSource::PublicAgreement,
            'amount' => 100,
            'transaction_date' => '2026-05-07',
        ]);
        Transaction::factory()->for($user)->income()->create([
            'source' => TransactionSource::IndividualDonation,
            'amount' => 300,
            'transaction_date' => '2026-05-06',
        ]);

        $breakdown = $this->service()->breakdownBySource(
            TransactionType::Income,
            Carbon::parse('2026-05-01'),
            Carbon::parse('2026-05-31'),
        );

        // Somadas por fonte e ordenadas da maior para a menor.
        $this->assertSame(['Convênio público', 'Doação de pessoa física'], $breakdown['labels']);
        $this->assertSame([800.0, 300.0], $breakdown['values']);
    }

    #[Test]
    public function composicao_de_saidas_ignora_as_entradas_do_periodo(): void
    {
        $user = User::factory()->create();

        Transaction::factory()->for($user)->income()->create([
            'source' => TransactionSource::PublicAgreement,
            'amount' => 900,
            'transaction_date' => '2026-05-05',
        ]);
        Transaction::factory()->for($user)->expense()->create([
            'source' => TransactionSource::Rent,
            'amount' => 2200,
            'transaction_date' => '2026-05-10',
        ]);

        $breakdown = $this->service()->breakdownBySource(
            TransactionType::Expense,
            Carbon::parse('2026-05-01'),
            Carbon::parse('2026-05-31'),
        );

        $this->assertSame(['Aluguel'], $breakdown['labels']);
        $this->assertSame([2200.0], $breakdown['values']);
    }
}
