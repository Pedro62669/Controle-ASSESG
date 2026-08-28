<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RecurrenceDuration;
use App\Enums\RecurrenceInterval;
use App\Enums\TransactionClassification;
use App\Livewire\Transactions\TransactionEditor;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cobre a configuração de recorrência das despesas: parcelamento, ciclo
 * indeterminado, intervalos largos e meses escolhidos manualmente.
 */
class RecurrenceTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): Testable
    {
        $this->actingAs(User::factory()->create());

        return Livewire::test(TransactionEditor::class)
            ->set('form.type', 'expense')
            ->set('form.source', 'equipment')
            ->set('form.classification', 'recurring')
            ->set('form.amount', '389,00')
            ->set('form.transaction_date', '2026-01-15')
            ->set('form.description', 'Parcelas do computador da secretaria.');
    }

    #[Test]
    public function despesa_recorrente_sem_ciclo_e_recusada(): void
    {
        $this->editor()
            ->call('save')
            ->assertHasErrors([
                'form.recurrence_interval' => 'required_if',
                'form.recurrence_duration' => 'required_if',
            ]);

        $this->assertSame(0, Transaction::query()->count());
    }

    #[Test]
    public function compra_parcelada_guarda_o_numero_de_parcelas(): void
    {
        $this->editor()
            ->set('form.recurrence_interval', 'monthly')
            ->set('form.recurrence_duration', 'installments')
            ->set('form.recurrence_count', 10)
            ->call('save')
            ->assertHasNoErrors();

        $transaction = Transaction::query()->sole();

        $this->assertSame(RecurrenceInterval::Monthly, $transaction->recurrence_interval);
        $this->assertSame(RecurrenceDuration::Installments, $transaction->recurrence_duration);
        $this->assertSame(10, $transaction->recurrence_count);
        $this->assertCount(10, $transaction->occurrences());
        $this->assertSame('10/2026', $transaction->recurrenceEndsAt()?->format('m/Y'));
    }

    #[Test]
    public function despesa_indeterminada_nao_guarda_parcelas(): void
    {
        $this->editor()
            ->set('form.recurrence_interval', 'monthly')
            ->set('form.recurrence_duration', 'installments')
            ->set('form.recurrence_count', 10)
            ->set('form.recurrence_duration', 'indeterminate')
            ->call('save')
            ->assertHasNoErrors();

        $transaction = Transaction::query()->sole();

        $this->assertNull($transaction->recurrence_count);
        $this->assertNull($transaction->recurrenceEndsAt());
        $this->assertStringContainsString('sem prazo definido', (string) $transaction->recurrenceSummary());
    }

    #[Test]
    public function intervalo_semestral_espaca_as_ocorrencias_em_seis_meses(): void
    {
        $this->editor()
            ->set('form.recurrence_interval', 'semiannual')
            ->set('form.recurrence_duration', 'installments')
            ->set('form.recurrence_count', 3)
            ->call('save')
            ->assertHasNoErrors();

        $dates = array_map(
            static fn ($date): string => $date->format('Y-m-d'),
            Transaction::query()->sole()->occurrences(),
        );

        $this->assertSame(['2026-01-15', '2026-07-15', '2027-01-15'], $dates);
    }

    #[Test]
    public function meses_especificos_geram_ocorrencias_apenas_nos_meses_escolhidos(): void
    {
        $this->editor()
            ->set('form.recurrence_interval', 'specific_months')
            ->call('toggleRecurrenceMonth', 5)
            ->call('toggleRecurrenceMonth', 6)
            ->set('form.recurrence_duration', 'indeterminate')
            ->call('save')
            ->assertHasNoErrors();

        $transaction = Transaction::query()->sole();

        $this->assertSame([5, 6], $transaction->recurrence_months);

        $dates = array_map(
            static fn ($date): string => $date->format('Y-m-d'),
            $transaction->occurrences(4),
        );

        $this->assertSame(['2026-05-15', '2026-06-15', '2027-05-15', '2027-06-15'], $dates);
        $this->assertStringContainsString('Mai, Jun', (string) $transaction->recurrenceSummary());
    }

    #[Test]
    public function marcar_e_desmarcar_o_mesmo_mes_remove_a_selecao(): void
    {
        $this->editor()
            ->set('form.recurrence_interval', 'specific_months')
            ->call('toggleRecurrenceMonth', 5)
            ->call('toggleRecurrenceMonth', 5)
            ->set('form.recurrence_duration', 'indeterminate')
            ->call('save')
            ->assertHasErrors(['form.recurrence_months' => 'required_if']);
    }

    #[Test]
    public function trocar_o_intervalo_descarta_os_meses_escolhidos(): void
    {
        $this->editor()
            ->set('form.recurrence_interval', 'specific_months')
            ->call('toggleRecurrenceMonth', 5)
            ->set('form.recurrence_interval', 'monthly')
            ->assertSet('form.recurrence_months', [])
            ->set('form.recurrence_duration', 'indeterminate')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull(Transaction::query()->sole()->recurrence_months);
    }

    #[Test]
    public function voltar_para_pontual_descarta_toda_a_recorrencia(): void
    {
        $this->editor()
            ->set('form.recurrence_interval', 'monthly')
            ->set('form.recurrence_duration', 'installments')
            ->set('form.recurrence_count', 12)
            ->set('form.classification', 'one_off')
            ->assertSet('form.recurrence_interval', null)
            ->assertSet('form.recurrence_count', null)
            ->call('save')
            ->assertHasNoErrors();

        $transaction = Transaction::query()->sole();

        $this->assertNull($transaction->recurrence_interval);
        $this->assertNull($transaction->recurrence_duration);
        $this->assertNull($transaction->recurrence_count);
        $this->assertNull($transaction->recurrenceSummary());
    }

    #[Test]
    public function entrada_pontual_nao_guarda_recorrencia(): void
    {
        $this->editor()
            ->set('form.recurrence_interval', 'monthly')
            ->set('form.recurrence_duration', 'indeterminate')
            ->set('form.type', 'income')
            ->set('form.source', 'individual_donation')
            ->set('form.classification', 'one_off')
            ->set('form.description', 'Doação recebida da campanha de arrecadação.')
            ->call('save')
            ->assertHasNoErrors();

        $transaction = Transaction::query()->sole();

        $this->assertSame(TransactionClassification::OneOff, $transaction->classification);
        $this->assertNull($transaction->recurrence_interval);
    }
}
