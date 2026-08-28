<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RecurrenceDuration;
use App\Enums\RecurrenceInterval;
use App\Enums\TransactionClassification;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CashFlowProjectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CashFlowProjectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-15'));

        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function service(): CashFlowProjectionService
    {
        return app(CashFlowProjectionService::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function recurring(array $attributes): Transaction
    {
        return Transaction::factory()->for($this->user)->create(array_merge([
            'classification' => TransactionClassification::Recurring,
            'recurrence_interval' => RecurrenceInterval::Monthly,
            'recurrence_duration' => RecurrenceDuration::Indeterminate,
            'recurrence_count' => null,
            'recurrence_months' => null,
        ], $attributes));
    }

    #[Test]
    public function entrada_recorrente_entra_na_projecao(): void
    {
        $this->recurring([
            'type' => TransactionType::Income,
            'source' => TransactionSource::PublicAgreement,
            'description' => 'Auxílio mensal do programa federal',
            'amount' => 2600,
            'transaction_date' => '2026-08-05',
        ]);

        $projection = $this->service()->project(3);

        // Agosto já foi lançado; setembro em diante é projeção.
        $this->assertSame([0.0, 2600.0, 2600.0], $projection['income']);
        $this->assertSame(5200.0, $projection['totals']['income']);
    }

    #[Test]
    public function saida_recorrente_entra_na_projecao(): void
    {
        $this->recurring([
            'type' => TransactionType::Expense,
            'source' => TransactionSource::Rent,
            'description' => 'Aluguel da sede',
            'amount' => 2200,
            'transaction_date' => '2026-08-05',
        ]);

        $projection = $this->service()->project(3);

        $this->assertSame([0.0, 2200.0, 2200.0], $projection['expense']);
    }

    #[Test]
    public function movimentacao_pontual_nao_entra_na_projecao(): void
    {
        Transaction::factory()->for($this->user)->create([
            'type' => TransactionType::Expense,
            'classification' => TransactionClassification::OneOff,
            'recurrence_interval' => null,
            'amount' => 5000,
            'transaction_date' => '2026-08-05',
        ]);

        $projection = $this->service()->project(6);

        $this->assertSame(0, $projection['series']);
        $this->assertSame(0.0, $projection['totals']['expense']);
    }

    #[Test]
    public function lancamentos_repetidos_da_mesma_serie_projetam_uma_vez_so(): void
    {
        // Oito meses de aluguel lançados: a série é uma só.
        foreach (['2026-01-05', '2026-02-05', '2026-03-05', '2026-08-05'] as $date) {
            $this->recurring([
                'type' => TransactionType::Expense,
                'source' => TransactionSource::Rent,
                'description' => 'Aluguel da sede',
                'amount' => 2200,
                'transaction_date' => $date,
            ]);
        }

        $projection = $this->service()->project(3);

        $this->assertSame(1, $projection['series']);
        $this->assertSame([0.0, 2200.0, 2200.0], $projection['expense']);
    }

    #[Test]
    public function parcelamento_projeta_apenas_as_parcelas_restantes(): void
    {
        // Compra em 4x com 3 parcelas já lançadas: resta apenas uma.
        foreach (['2026-06-10', '2026-07-10', '2026-08-10'] as $date) {
            $this->recurring([
                'type' => TransactionType::Expense,
                'source' => TransactionSource::Equipment,
                'description' => 'Computador da secretaria',
                'amount' => 389,
                'transaction_date' => $date,
                'recurrence_duration' => RecurrenceDuration::Installments,
                'recurrence_count' => 4,
            ]);
        }

        $projection = $this->service()->project(6);

        $this->assertSame(389.0, $projection['totals']['expense']);
        $this->assertSame(389.0, $projection['expense'][1]);
        $this->assertSame(0.0, $projection['expense'][2]);
    }

    #[Test]
    public function parcelamento_encerrado_nao_projeta_nada(): void
    {
        foreach (['2026-07-10', '2026-08-10'] as $date) {
            $this->recurring([
                'type' => TransactionType::Expense,
                'source' => TransactionSource::Equipment,
                'description' => 'Impressora',
                'amount' => 300,
                'transaction_date' => $date,
                'recurrence_duration' => RecurrenceDuration::Installments,
                'recurrence_count' => 2,
            ]);
        }

        $this->assertSame(0.0, $this->service()->project(6)['totals']['expense']);
    }

    #[Test]
    public function intervalo_semestral_projeta_a_cada_seis_meses(): void
    {
        $this->recurring([
            'type' => TransactionType::Income,
            'source' => TransactionSource::CompanyDonation,
            'description' => 'Doação semestral da empresa madrinha',
            'amount' => 7500,
            'transaction_date' => '2026-08-05',
            'recurrence_interval' => RecurrenceInterval::Semiannual,
        ]);

        $projection = $this->service()->project(12);

        // Agosto já lançado; a próxima cai em fevereiro de 2027.
        $this->assertSame(7500.0, $projection['totals']['income']);
        $this->assertSame(7500.0, $projection['income'][6]);
    }

    #[Test]
    public function saldo_projetado_parte_do_caixa_atual_e_acumula(): void
    {
        Transaction::factory()->for($this->user)->income()->create([
            'amount' => 10000,
            'transaction_date' => '2026-08-01',
        ]);

        $this->recurring([
            'type' => TransactionType::Expense,
            'source' => TransactionSource::Rent,
            'description' => 'Aluguel da sede',
            'amount' => 1000,
            'transaction_date' => '2026-08-05',
        ]);

        $projection = $this->service()->project(3);

        // O caixa de hoje já é 9.000: a entrada de 10.000 menos o aluguel de
        // agosto, que foi efetivamente lançado. A projeção parte daí e desconta
        // 1.000 em cada mês seguinte.
        $this->assertSame([9000.0, 8000.0, 7000.0], $projection['balance']);
        $this->assertSame(7000.0, $projection['totals']['finalBalance']);
    }

    #[Test]
    public function horizonte_invalido_cai_no_padrao_de_seis_meses(): void
    {
        $this->assertSame(6, $this->service()->project(99)['months']);
        $this->assertCount(12, $this->service()->project(12)['labels']);
    }
}
