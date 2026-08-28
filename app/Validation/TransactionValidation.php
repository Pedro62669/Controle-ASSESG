<?php

declare(strict_types=1);

namespace App\Validation;

use App\Enums\RecurrenceDuration;
use App\Enums\RecurrenceInterval;
use App\Enums\TransactionClassification;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Rules\SourceMatchesTransactionType;
use Illuminate\Validation\Rule;

/**
 * Fonte única das regras de transação, compartilhada entre os Form Requests
 * (fluxo HTTP) e os componentes Livewire (fluxo reativo).
 */
final class TransactionValidation
{
    /**
     * Tamanho mínimo da justificativa exigida quando não há comprovante.
     */
    public const int MIN_DESCRIPTION_LENGTH = 15;

    /**
     * Tamanho máximo do anexo, em kilobytes.
     */
    public const int MAX_DOCUMENT_SIZE = 5120;

    /**
     * Limites do número de parcelas de uma despesa recorrente.
     */
    public const int MIN_INSTALLMENTS = 2;

    public const int MAX_INSTALLMENTS = 360;

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'type' => [
                'required',
                Rule::enum(TransactionType::class),
            ],
            // Origem do dinheiro que entra ou destino do que sai: é a
            // dimensão pela qual os relatórios agrupam as movimentações.
            'source' => [
                'required',
                Rule::enum(TransactionSource::class),
                new SourceMatchesTransactionType,
            ],
            // Entradas e saídas são classificadas: um auxílio mensal é tão
            // recorrente quanto o aluguel.
            'classification' => [
                'required',
                Rule::enum(TransactionClassification::class),
            ],
            // A recorrência é configurada em qualquer movimentação recorrente;
            // cada campo abaixo depende da escolha feita no anterior.
            'recurrence_interval' => [
                'nullable',
                'required_if:classification,'.TransactionClassification::Recurring->value,
                Rule::enum(RecurrenceInterval::class),
            ],
            'recurrence_duration' => [
                'nullable',
                'required_if:classification,'.TransactionClassification::Recurring->value,
                Rule::enum(RecurrenceDuration::class),
            ],
            'recurrence_count' => [
                'nullable',
                'required_if:recurrence_duration,'.RecurrenceDuration::Installments->value,
                'integer',
                'min:'.self::MIN_INSTALLMENTS,
                'max:'.self::MAX_INSTALLMENTS,
            ],
            'recurrence_months' => [
                'nullable',
                // required_if já recusa a lista vazia; um min:1 aqui dispararia
                // também nos intervalos em que o campo não se aplica.
                'required_if:recurrence_interval,'.RecurrenceInterval::SpecificMonths->value,
                'array',
                'max:12',
            ],
            'recurrence_months.*' => [
                'integer',
                'between:1,12',
            ],
            'amount' => [
                'required',
                'numeric',
                'gt:0',
                'max:999999999999.99',
            ],
            'transaction_date' => [
                'required',
                'date',
                'before_or_equal:today',
            ],
            'document_path' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'mimetypes:application/pdf,image/jpeg,image/png',
                'max:'.self::MAX_DOCUMENT_SIZE,
            ],
            // Regra condicional crítica: sem comprovante anexado, a descrição
            // passa a ser obrigatória e precisa justificar a movimentação.
            'description' => [
                'required_without:document_path',
                'nullable',
                'string',
                'min:'.self::MIN_DESCRIPTION_LENGTH,
                'max:1000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'type.required' => 'Informe se a movimentação é uma entrada ou uma saída.',
            'source.required' => 'Informe a fonte da movimentação.',
            'source.enum' => 'Selecione uma fonte válida.',
            'classification.required' => 'Informe se a movimentação é pontual ou recorrente.',
            'classification.enum' => 'Selecione uma classificação válida.',
            'recurrence_interval.required_if' => 'Informe com que frequência esta despesa se repete.',
            'recurrence_interval.enum' => 'Selecione um intervalo de recorrência válido.',
            'recurrence_duration.required_if' => 'Informe se a recorrência tem prazo definido ou é indeterminada.',
            'recurrence_duration.enum' => 'Selecione uma duração de recorrência válida.',
            'recurrence_count.required_if' => 'Informe o número de parcelas.',
            'recurrence_count.integer' => 'O número de parcelas deve ser um número inteiro.',
            'recurrence_count.min' => 'Uma despesa parcelada precisa de ao menos '.self::MIN_INSTALLMENTS.' parcelas.',
            'recurrence_count.max' => 'O número de parcelas deve ser no máximo '.self::MAX_INSTALLMENTS.'.',
            'recurrence_months.required_if' => 'Selecione ao menos um mês em que a despesa ocorre.',
            'recurrence_months.*.between' => 'Selecione apenas meses válidos.',
            'amount.required' => 'Informe o valor da movimentação.',
            'amount.numeric' => 'O valor deve ser um número.',
            'amount.gt' => 'O valor deve ser maior que zero.',
            'transaction_date.required' => 'Informe a data da movimentação.',
            'transaction_date.date' => 'Informe uma data válida.',
            'transaction_date.before_or_equal' => 'A data da movimentação não pode ser futura.',
            'document_path.mimes' => 'O comprovante deve ser um arquivo PDF, JPG ou PNG.',
            'document_path.mimetypes' => 'O comprovante deve ser um arquivo PDF, JPG ou PNG.',
            'document_path.max' => 'O comprovante deve ter no máximo 5 MB.',
            'description.required_without' => 'Sem comprovante anexado, a descrição é obrigatória: justifique a origem ou o destino do dinheiro.',
            'description.min' => 'A justificativa deve ter no mínimo '.self::MIN_DESCRIPTION_LENGTH.' caracteres.',
            'description.max' => 'A descrição deve ter no máximo 1000 caracteres.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'type' => 'tipo',
            'source' => 'fonte',
            'classification' => 'classificação',
            'recurrence_interval' => 'intervalo de recorrência',
            'recurrence_duration' => 'duração da recorrência',
            'recurrence_count' => 'número de parcelas',
            'recurrence_months' => 'meses de ocorrência',
            'amount' => 'valor',
            'transaction_date' => 'data da movimentação',
            'document_path' => 'comprovante',
            'description' => 'descrição',
        ];
    }
}
