<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TransactionClassification;
use App\Enums\TransactionType;
use App\Livewire\Transactions\TransactionEditor;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cobre o componente de registro de movimentação, com foco na regra
 * condicional entre comprovante e descrição.
 */
class TransactionEditorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sem_comprovante_e_sem_descricao_o_registro_e_recusado(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(TransactionEditor::class)
            ->set('form.type', 'expense')
            ->set('form.source', 'consumable_supplies')
            ->set('form.classification', 'one_off')
            ->set('form.amount', '320,00')
            ->set('form.transaction_date', now()->toDateString())
            ->call('save')
            ->assertHasErrors(['form.description' => 'required_without']);

        $this->assertSame(0, Transaction::query()->count());
    }

    #[Test]
    public function descricao_justificada_permite_registrar_sem_comprovante(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(TransactionEditor::class)
            ->set('form.type', 'income')
            ->set('form.source', 'individual_donation')
            ->set('form.classification', 'one_off')
            ->set('form.amount', '1.250,75')
            ->set('form.transaction_date', now()->toDateString())
            ->set('form.description', 'Doação recebida da campanha de arrecadação.')
            ->call('save')
            ->assertHasNoErrors();

        $transaction = Transaction::query()->sole();

        $this->assertSame('1250.75', (string) $transaction->amount);
        $this->assertSame($user->getKey(), $transaction->user_id);
    }

    #[Test]
    public function comprovante_anexado_dispensa_a_descricao_e_e_gravado_fora_do_publico(): void
    {
        Storage::fake('local');

        $this->actingAs(User::factory()->create());

        Livewire::test(TransactionEditor::class)
            ->set('form.type', 'expense')
            ->set('form.source', 'consumable_supplies')
            ->set('form.classification', 'one_off')
            ->set('form.amount', '89,90')
            ->set('form.transaction_date', now()->toDateString())
            ->set('form.document_path', UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf'))
            ->call('save')
            ->assertHasNoErrors();

        $transaction = Transaction::query()->sole();

        $this->assertNotNull($transaction->document_path);
        $this->assertSame('nota.pdf', $transaction->document_name);
        Storage::disk('local')->assertExists($transaction->document_path);
    }

    #[Test]
    public function saida_sem_classificacao_e_recusada(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(TransactionEditor::class)
            ->set('form.type', 'expense')
            ->set('form.amount', '450,00')
            ->set('form.transaction_date', now()->toDateString())
            ->set('form.description', 'Compra de material de limpeza para as oficinas.')
            ->call('save')
            ->assertHasErrors(['form.classification' => 'required']);

        $this->assertSame(0, Transaction::query()->count());
    }

    #[Test]
    public function saida_classificada_e_registrada(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(TransactionEditor::class)
            ->set('form.type', 'expense')
            ->set('form.source', 'rent')
            ->set('form.classification', 'recurring')
            ->set('form.recurrence_interval', 'monthly')
            ->set('form.recurrence_duration', 'indeterminate')
            ->set('form.amount', '2.200,00')
            ->set('form.transaction_date', now()->toDateString())
            ->set('form.description', 'Aluguel da sede administrativa da associação.')
            ->call('save')
            ->assertHasNoErrors();

        $transaction = Transaction::query()->sole();

        $this->assertSame(TransactionType::Expense, $transaction->type);
        $this->assertSame(TransactionClassification::Recurring, $transaction->classification);
    }

    #[Test]
    public function trocar_o_tipo_descarta_uma_fonte_incompativel(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(TransactionEditor::class)
            ->set('form.type', 'expense')
            ->set('form.source', 'rent')
            ->set('form.type', 'income')
            ->assertSet('form.source', null);
    }

    #[Test]
    public function trocar_o_tipo_preserva_a_classificacao(): void
    {
        $this->actingAs(User::factory()->create());

        // Classificação vale para os dois tipos, então sobrevive à troca.
        Livewire::test(TransactionEditor::class)
            ->set('form.type', 'expense')
            ->set('form.classification', 'one_off')
            ->set('form.type', 'income')
            ->assertSet('form.classification', 'one_off');
    }

    #[Test]
    public function a_lista_de_fontes_acompanha_o_tipo_selecionado(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(TransactionEditor::class)
            ->call('openEditor')
            ->set('form.type', 'income')
            ->assertSee('Convênio público')
            ->assertDontSee('Aluguel')
            ->set('form.type', 'expense')
            ->assertSee('Aluguel')
            ->assertDontSee('Convênio público');
    }

    #[Test]
    public function movimentacao_sem_fonte_e_recusada(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(TransactionEditor::class)
            ->set('form.type', 'income')
            ->set('form.classification', 'one_off')
            ->set('form.amount', '500,00')
            ->set('form.transaction_date', now()->toDateString())
            ->set('form.description', 'Doação recebida da campanha de arrecadação.')
            ->call('save')
            ->assertHasErrors(['form.source' => 'required']);

        $this->assertSame(0, Transaction::query()->count());
    }

    #[Test]
    public function valor_zero_e_recusado(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(TransactionEditor::class)
            ->set('form.type', 'income')
            ->set('form.source', 'individual_donation')
            ->set('form.classification', 'one_off')
            ->set('form.amount', '0,00')
            ->set('form.transaction_date', now()->toDateString())
            ->set('form.description', 'Justificativa suficientemente longa para o teste.')
            ->call('save')
            ->assertHasErrors(['form.amount']);
    }

    #[Test]
    public function usuario_nao_edita_movimentacao_de_outro(): void
    {
        $autor = User::factory()->create();
        $outro = User::factory()->create();

        $transaction = Transaction::factory()->for($autor)->create();

        $this->actingAs($outro);

        Livewire::test(TransactionEditor::class)
            ->call('openEditor', $transaction->getKey())
            ->assertForbidden();
    }

    #[Test]
    public function administrador_principal_edita_movimentacao_de_qualquer_usuario(): void
    {
        $autor = User::factory()->create();
        $admin = User::factory()->mainAdmin()->create();

        $transaction = Transaction::factory()->for($autor)->create([
            'description' => 'Justificativa original suficientemente longa.',
        ]);

        $this->actingAs($admin);

        Livewire::test(TransactionEditor::class)
            ->call('openEditor', $transaction->getKey())
            ->set('form.description', 'Justificativa corrigida pelo administrador principal.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(
            'Justificativa corrigida pelo administrador principal.',
            $transaction->refresh()->description,
        );
    }
}
