<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Validation\TransactionValidation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cobre a regra condicional crítica: sem comprovante, a descrição é
 * obrigatória e precisa justificar a movimentação.
 */
class TransactionValidationTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'income',
            'source' => 'public_agreement',
            'classification' => 'one_off',
            'recurrence_interval' => null,
            'recurrence_duration' => null,
            'recurrence_count' => null,
            'recurrence_months' => null,
            'amount' => '150.00',
            'transaction_date' => now()->toDateString(),
            'description' => null,
            'document_path' => null,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validate(array $payload): \Illuminate\Validation\Validator
    {
        return Validator::make(
            $payload,
            TransactionValidation::rules(),
            TransactionValidation::messages(),
            TransactionValidation::attributes(),
        );
    }

    #[Test]
    public function descricao_e_obrigatoria_quando_nao_ha_comprovante(): void
    {
        $validator = $this->validate($this->payload());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('description', $validator->errors()->toArray());
    }

    #[Test]
    public function descricao_precisa_de_pelo_menos_quinze_caracteres(): void
    {
        $validator = $this->validate($this->payload(['description' => 'Doação']));

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('15 caracteres', $validator->errors()->first('description'));
    }

    #[Test]
    public function descricao_longa_dispensa_o_comprovante(): void
    {
        $validator = $this->validate($this->payload([
            'description' => 'Doação recebida da campanha de arrecadação de novembro.',
        ]));

        $this->assertFalse($validator->fails(), (string) $validator->errors());
    }

    #[Test]
    public function comprovante_dispensa_a_descricao(): void
    {
        $validator = $this->validate($this->payload([
            'document_path' => UploadedFile::fake()->create('comprovante.pdf', 120, 'application/pdf'),
        ]));

        $this->assertFalse($validator->fails(), (string) $validator->errors());
    }

    #[Test]
    public function comprovante_com_extensao_nao_permitida_e_recusado(): void
    {
        $validator = $this->validate($this->payload([
            'document_path' => UploadedFile::fake()->create('planilha.xlsx', 40, 'application/vnd.ms-excel'),
        ]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('document_path', $validator->errors()->toArray());
    }

    #[Test]
    public function fonte_e_obrigatoria(): void
    {
        $validator = $this->validate($this->payload([
            'source' => null,
            'description' => 'Doação recebida da campanha de arrecadação.',
        ]));

        $this->assertTrue($validator->fails());
        $this->assertSame('Informe a fonte da movimentação.', $validator->errors()->first('source'));
    }

    #[Test]
    public function fonte_de_saida_nao_serve_para_entrada(): void
    {
        $validator = $this->validate($this->payload([
            'type' => 'income',
            'source' => 'rent',
            'description' => 'Doação recebida da campanha de arrecadação.',
        ]));

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('não é válida para uma entrada', $validator->errors()->first('source'));
    }

    #[Test]
    public function fonte_de_entrada_nao_serve_para_saida(): void
    {
        $validator = $this->validate($this->payload([
            'type' => 'expense',
            'source' => 'public_agreement',
            'classification' => 'one_off',
            'description' => 'Compra de material de limpeza para as oficinas.',
        ]));

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('não é válida para uma saída', $validator->errors()->first('source'));
    }

    #[Test]
    public function fonte_inexistente_e_recusada(): void
    {
        $validator = $this->validate($this->payload([
            'source' => 'doacao_de_marte',
            'description' => 'Doação recebida da campanha de arrecadação.',
        ]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('source', $validator->errors()->toArray());
    }

    #[Test]
    public function toda_movimentacao_exige_classificacao(): void
    {
        foreach (['income' => 'public_agreement', 'expense' => 'consumable_supplies'] as $type => $source) {
            $validator = $this->validate($this->payload([
                'type' => $type,
                'source' => $source,
                'classification' => null,
                'description' => 'Justificativa suficientemente longa para o teste.',
            ]));

            $this->assertTrue($validator->fails(), "{$type} deveria exigir classificação.");
            $this->assertSame(
                'Informe se a movimentação é pontual ou recorrente.',
                $validator->errors()->first('classification'),
            );
        }
    }

    #[Test]
    public function saida_com_classificacao_valida_e_aceita(): void
    {
        // A recorrente exige, além da classificação, a configuração do ciclo.
        $casos = [
            ['classification' => 'one_off'],
            [
                'classification' => 'recurring',
                'recurrence_interval' => 'monthly',
                'recurrence_duration' => 'indeterminate',
            ],
        ];

        foreach ($casos as $caso) {
            $validator = $this->validate($this->payload(array_merge([
                'type' => 'expense',
                'source' => 'consumable_supplies',
                'description' => 'Compra de material de limpeza para as oficinas.',
            ], $caso)));

            $this->assertFalse($validator->fails(), (string) $validator->errors());
        }
    }

    #[Test]
    public function classificacao_desconhecida_e_recusada(): void
    {
        $validator = $this->validate($this->payload([
            'type' => 'expense',
            'source' => 'consumable_supplies',
            'classification' => 'mensal',
            'description' => 'Compra de material de limpeza para as oficinas.',
        ]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('classification', $validator->errors()->toArray());
    }

    #[Test]
    public function entrada_pode_ser_recorrente(): void
    {
        $validator = $this->validate($this->payload([
            'type' => 'income',
            'source' => 'public_agreement',
            'classification' => 'recurring',
            'recurrence_interval' => 'monthly',
            'recurrence_duration' => 'indeterminate',
            'description' => 'Auxílio mensal do programa federal de assistência.',
        ]));

        $this->assertFalse($validator->fails(), (string) $validator->errors());
    }

    #[Test]
    public function entrada_recorrente_sem_ciclo_e_recusada(): void
    {
        $validator = $this->validate($this->payload([
            'type' => 'income',
            'source' => 'public_agreement',
            'classification' => 'recurring',
            'description' => 'Auxílio mensal do programa federal de assistência.',
        ]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('recurrence_interval', $validator->errors()->toArray());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function recurringPayload(array $overrides = []): array
    {
        return $this->payload(array_merge([
            'type' => 'expense',
            'source' => 'rent',
            'classification' => 'recurring',
            'description' => 'Mensalidade do serviço de internet da sede.',
        ], $overrides));
    }

    #[Test]
    public function despesa_recorrente_exige_intervalo_e_duracao(): void
    {
        $validator = $this->validate($this->recurringPayload());

        $this->assertTrue($validator->fails());

        $errors = $validator->errors()->toArray();

        $this->assertArrayHasKey('recurrence_interval', $errors);
        $this->assertArrayHasKey('recurrence_duration', $errors);
    }

    #[Test]
    public function recorrencia_indeterminada_dispensa_numero_de_parcelas(): void
    {
        $validator = $this->validate($this->recurringPayload([
            'recurrence_interval' => 'monthly',
            'recurrence_duration' => 'indeterminate',
        ]));

        $this->assertFalse($validator->fails(), (string) $validator->errors());
    }

    #[Test]
    public function recorrencia_parcelada_exige_o_numero_de_parcelas(): void
    {
        $validator = $this->validate($this->recurringPayload([
            'recurrence_interval' => 'monthly',
            'recurrence_duration' => 'installments',
        ]));

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'Informe o número de parcelas.',
            $validator->errors()->first('recurrence_count'),
        );
    }

    #[Test]
    public function numero_de_parcelas_fora_do_intervalo_e_recusado(): void
    {
        foreach ([1, 400] as $count) {
            $validator = $this->validate($this->recurringPayload([
                'recurrence_interval' => 'monthly',
                'recurrence_duration' => 'installments',
                'recurrence_count' => $count,
            ]));

            $this->assertTrue($validator->fails(), "{$count} parcelas deveria ser recusado.");
            $this->assertArrayHasKey('recurrence_count', $validator->errors()->toArray());
        }
    }

    #[Test]
    public function parcelamento_valido_e_aceito(): void
    {
        $validator = $this->validate($this->recurringPayload([
            'recurrence_interval' => 'monthly',
            'recurrence_duration' => 'installments',
            'recurrence_count' => 10,
        ]));

        $this->assertFalse($validator->fails(), (string) $validator->errors());
    }

    #[Test]
    public function meses_especificos_exigem_a_selecao_dos_meses(): void
    {
        $validator = $this->validate($this->recurringPayload([
            'recurrence_interval' => 'specific_months',
            'recurrence_duration' => 'indeterminate',
        ]));

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'Selecione ao menos um mês em que a despesa ocorre.',
            $validator->errors()->first('recurrence_months'),
        );
    }

    #[Test]
    public function mes_invalido_e_recusado(): void
    {
        $validator = $this->validate($this->recurringPayload([
            'recurrence_interval' => 'specific_months',
            'recurrence_duration' => 'indeterminate',
            'recurrence_months' => [5, 13],
        ]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('recurrence_months.1', $validator->errors()->toArray());
    }

    #[Test]
    public function meses_especificos_validos_sao_aceitos(): void
    {
        $validator = $this->validate($this->recurringPayload([
            'recurrence_interval' => 'specific_months',
            'recurrence_duration' => 'indeterminate',
            'recurrence_months' => [5, 6],
        ]));

        $this->assertFalse($validator->fails(), (string) $validator->errors());
    }

    #[Test]
    public function despesa_pontual_nao_exige_configuracao_de_recorrencia(): void
    {
        $validator = $this->validate($this->payload([
            'type' => 'expense',
            'source' => 'consumable_supplies',
            'classification' => 'one_off',
            'description' => 'Compra de material de limpeza para as oficinas.',
        ]));

        $this->assertFalse($validator->fails(), (string) $validator->errors());
    }

    #[Test]
    public function valor_precisa_ser_maior_que_zero(): void
    {
        foreach (['0', '0.00', '-25.50'] as $amount) {
            $validator = $this->validate($this->payload([
                'amount' => $amount,
                'description' => 'Justificativa suficientemente longa para o teste.',
            ]));

            $this->assertTrue($validator->fails(), "O valor {$amount} deveria ser recusado.");
            $this->assertSame('O valor deve ser maior que zero.', $validator->errors()->first('amount'));
        }
    }

    #[Test]
    public function data_da_movimentacao_e_obrigatoria_e_nao_pode_ser_futura(): void
    {
        $semData = $this->validate($this->payload([
            'transaction_date' => null,
            'description' => 'Justificativa suficientemente longa para o teste.',
        ]));

        $this->assertTrue($semData->fails());
        $this->assertSame('Informe a data da movimentação.', $semData->errors()->first('transaction_date'));

        $dataFutura = $this->validate($this->payload([
            'transaction_date' => now()->addDay()->toDateString(),
            'description' => 'Justificativa suficientemente longa para o teste.',
        ]));

        $this->assertTrue($dataFutura->fails());
        $this->assertArrayHasKey('transaction_date', $dataFutura->errors()->toArray());
    }
}
