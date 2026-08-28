<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Enums\RecurrenceDuration;
use App\Enums\RecurrenceInterval;
use App\Enums\TransactionClassification;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Validation\TransactionValidation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Form;

/**
 * Form object da movimentação financeira.
 *
 * Os nomes das propriedades espelham as colunas da tabela para que as regras
 * de TransactionValidation — em especial required_without:document_path —
 * valham exatamente igual no Livewire e no fluxo HTTP com Form Request.
 */
class TransactionForm extends Form
{
    public ?int $transactionId = null;

    public string $type = 'income';

    /**
     * Fonte da movimentação; sempre coerente com o tipo selecionado.
     */
    public ?string $source = null;

    /**
     * Classificação da saída; permanece nula quando o tipo é entrada.
     */
    public ?string $classification = null;

    /**
     * Configuração da recorrência; toda nula fora das saídas recorrentes.
     */
    public ?string $recurrence_interval = null;

    public ?string $recurrence_duration = null;

    public ?int $recurrence_count = null;

    /**
     * Meses (1–12) escolhidos quando o intervalo é "meses específicos".
     *
     * @var list<int>
     */
    public array $recurrence_months = [];

    public string $amount = '';

    public string $transaction_date = '';

    public ?string $description = null;

    /**
     * Novo anexo enviado nesta edição.
     */
    public ?TemporaryUploadedFile $document_path = null;

    /**
     * Caminho do comprovante já persistido (preenchido apenas na edição).
     */
    public ?string $storedDocumentPath = null;

    public ?string $storedDocumentName = null;

    /**
     * Marca o comprovante existente para remoção ao salvar.
     */
    public bool $removeDocument = false;

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        $rules = TransactionValidation::rules();

        // Na edição, um comprovante já persistido satisfaz a condição: exigir
        // descrição novamente seria travar a edição de um lançamento válido.
        if ($this->keepsStoredDocument()) {
            $rules['description'] = array_values(
                array_filter(
                    $rules['description'],
                    static fn (mixed $rule): bool => $rule !== 'required_without:document_path',
                ),
            );
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return TransactionValidation::messages();
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return TransactionValidation::attributes();
    }

    public function setTransaction(Transaction $transaction): void
    {
        $this->transactionId = $transaction->getKey();
        $this->type = $transaction->type->value;
        $this->source = $transaction->source?->value;
        $this->classification = $transaction->classification?->value;
        $this->recurrence_interval = $transaction->recurrence_interval?->value;
        $this->recurrence_duration = $transaction->recurrence_duration?->value;
        $this->recurrence_count = $transaction->recurrence_count;
        $this->recurrence_months = array_map('intval', $transaction->recurrence_months ?? []);
        $this->amount = number_format((float) $transaction->amount, 2, ',', '.');
        $this->transaction_date = $transaction->transaction_date->toDateString();
        $this->description = $transaction->description;
        $this->storedDocumentPath = $transaction->document_path;
        $this->storedDocumentName = $transaction->document_name;
        $this->document_path = null;
        $this->removeDocument = false;
    }

    public function setDefaults(): void
    {
        $this->type = TransactionType::Income->value;
        $this->source = null;
        $this->classification = null;
        $this->clearRecurrence();
        $this->transaction_date = Carbon::today()->toDateString();
    }

    /**
     * Persiste a movimentação (criação ou edição) junto com o anexo.
     *
     * O arquivo só vai para o disco depois que a linha é gravada, e a
     * transação de banco garante que um erro não deixe arquivo órfão.
     */
    public function save(User $author): Transaction
    {
        $this->validate();

        $newDocument = $this->document_path;
        $previousPath = $this->storedDocumentPath;
        $shouldReplace = $newDocument !== null;
        $shouldDrop = $this->removeDocument && ! $shouldReplace;

        $storedPath = null;

        $transaction = DB::transaction(function () use ($author, $newDocument, $shouldReplace, $shouldDrop, &$storedPath): Transaction {
            $transaction = $this->transactionId !== null
                ? Transaction::query()->findOrFail($this->transactionId)
                : new Transaction(['user_id' => $author->getKey()]);

            if ($shouldReplace) {
                $storedPath = $this->storeDocument($newDocument);
            }

            $type = TransactionType::from($this->type);

            $transaction->fill([
                'type' => $type,
                'source' => TransactionSource::tryFrom((string) $this->source),
                'classification' => TransactionClassification::tryFrom((string) $this->classification),
                ...$this->recurrenceAttributes(),
                'amount' => $this->normalizedAmount(),
                'transaction_date' => $this->transaction_date,
                'description' => $this->normalizedDescription(),
            ]);

            if ($shouldReplace) {
                $transaction->document_path = $storedPath;
                $transaction->document_name = $newDocument->getClientOriginalName();
            } elseif ($shouldDrop) {
                $transaction->document_path = null;
                $transaction->document_name = null;
            }

            $transaction->save();

            return $transaction;
        });

        // Fora da transação de banco: o arquivo antigo só é descartado depois
        // que o novo caminho está efetivamente commitado.
        if (($shouldReplace || $shouldDrop) && filled($previousPath)) {
            Storage::disk('local')->delete($previousPath);
        }

        $this->setTransaction($transaction->refresh());

        return $transaction;
    }

    public function resetForm(): void
    {
        $this->reset();
        $this->resetErrorBag();
        $this->setDefaults();
    }

    /**
     * Atributos de recorrência já normalizados para persistência.
     *
     * @return array<string, mixed>
     */
    private function recurrenceAttributes(): array
    {
        if (! $this->isRecurring()) {
            return [
                'recurrence_interval' => null,
                'recurrence_duration' => null,
                'recurrence_count' => null,
                'recurrence_months' => null,
            ];
        }

        $interval = RecurrenceInterval::tryFrom((string) $this->recurrence_interval);
        $duration = RecurrenceDuration::tryFrom((string) $this->recurrence_duration);

        return [
            'recurrence_interval' => $interval,
            'recurrence_duration' => $duration,
            'recurrence_count' => $duration === RecurrenceDuration::Installments
                ? $this->recurrence_count
                : null,
            'recurrence_months' => $interval?->requiresMonthSelection() === true
                ? $this->sortedMonths()
                : null,
        ];
    }

    /**
     * @return list<int>
     */
    public function sortedMonths(): array
    {
        $months = array_values(array_unique(array_map('intval', $this->recurrence_months)));
        sort($months);

        return $months;
    }

    public function isRecurring(): bool
    {
        return $this->classification === TransactionClassification::Recurring->value;
    }

    public function recurrenceIntervalEnum(): ?RecurrenceInterval
    {
        return RecurrenceInterval::tryFrom((string) $this->recurrence_interval);
    }

    public function recurrenceDurationEnum(): ?RecurrenceDuration
    {
        return RecurrenceDuration::tryFrom((string) $this->recurrence_duration);
    }

    public function needsMonthSelection(): bool
    {
        return $this->isRecurring()
            && $this->recurrenceIntervalEnum()?->requiresMonthSelection() === true;
    }

    public function needsInstallmentCount(): bool
    {
        return $this->isRecurring()
            && $this->recurrenceDurationEnum()?->requiresCount() === true;
    }

    public function toggleMonth(int $month): void
    {
        if ($month < 1 || $month > 12) {
            return;
        }

        $months = $this->sortedMonths();

        $this->recurrence_months = in_array($month, $months, true)
            ? array_values(array_diff($months, [$month]))
            : [...$months, $month];

        sort($this->recurrence_months);
    }

    public function hasMonth(int $month): bool
    {
        return in_array($month, array_map('intval', $this->recurrence_months), true);
    }

    public function clearRecurrence(): void
    {
        $this->recurrence_interval = null;
        $this->recurrence_duration = null;
        $this->recurrence_count = null;
        $this->recurrence_months = [];
    }

    /**
     * Descarta os campos que deixaram de fazer sentido após uma troca de
     * classificação, intervalo ou duração.
     */
    public function syncRecurrenceWithSelection(): void
    {
        if (! $this->isRecurring()) {
            $this->clearRecurrence();

            return;
        }

        if ($this->recurrenceIntervalEnum()?->requiresMonthSelection() !== true) {
            $this->recurrence_months = [];
        }

        if ($this->recurrenceDurationEnum()?->requiresCount() !== true) {
            $this->recurrence_count = null;
        }
    }

    public function isExpense(): bool
    {
        return $this->type === TransactionType::Expense->value;
    }

    /**
     * Limpa a classificação ao deixar de ser uma saída.
     */
    public function syncClassificationWithType(): void
    {
        $this->syncSourceWithType();
        $this->syncRecurrenceWithSelection();
    }

    /**
     * Uma fonte de saída não sobrevive à troca para entrada.
     */
    public function syncSourceWithType(): void
    {
        $source = TransactionSource::tryFrom((string) $this->source);

        if ($source !== null && $source->type()->value !== $this->type) {
            $this->source = null;
        }
    }

    /**
     * @return list<TransactionSource>
     */
    public function availableSources(): array
    {
        $type = TransactionType::tryFrom($this->type) ?? TransactionType::Income;

        return TransactionSource::casesFor($type);
    }

    public function isEditing(): bool
    {
        return $this->transactionId !== null;
    }

    public function currentDocumentName(): ?string
    {
        if ($this->document_path !== null) {
            return $this->document_path->getClientOriginalName();
        }

        return $this->keepsStoredDocument()
            ? ($this->storedDocumentName ?? basename((string) $this->storedDocumentPath))
            : null;
    }

    /**
     * Indica se, ao salvar, a movimentação continuará com um comprovante.
     */
    public function keepsStoredDocument(): bool
    {
        return filled($this->storedDocumentPath) && ! $this->removeDocument;
    }

    public function requiresDescription(): bool
    {
        return $this->document_path === null && ! $this->keepsStoredDocument();
    }

    /**
     * Hook do Livewire, equivalente ao prepareForValidation() do Form Request:
     * normaliza os dados imediatamente antes de as regras serem aplicadas.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function prepareForValidation($attributes)
    {
        if (array_key_exists('amount', $attributes)) {
            $attributes['amount'] = self::normalizeAmountInput((string) $attributes['amount']);
        }

        if (array_key_exists('description', $attributes)) {
            $description = trim((string) $attributes['description']);
            $attributes['description'] = $description === '' ? null : $description;
        }

        return $attributes;
    }

    public function normalizedAmount(): float
    {
        return (float) self::normalizeAmountInput($this->amount);
    }

    /**
     * Converte o valor digitado no padrão brasileiro (1.234,56) para decimal.
     *
     * Texto que não corresponda a um número é devolvido intacto, para que a
     * regra `numeric` produza a mensagem correta em vez de virar zero.
     */
    private static function normalizeAmountInput(string $amount): string
    {
        $raw = trim($amount);

        if ($raw === '') {
            return $raw;
        }

        // Formato brasileiro: a vírgula é o separador decimal e o ponto,
        // opcional, separa os milhares (1.234,56).
        if (preg_match('/^-?\d{1,3}(\.\d{3})*,\d{1,2}$|^-?\d+,\d{1,2}$/', $raw) === 1) {
            return str_replace(',', '.', str_replace('.', '', $raw));
        }

        return $raw;
    }

    private function normalizedDescription(): ?string
    {
        $description = trim((string) $this->description);

        return $description === '' ? null : $description;
    }

    /**
     * Nome de arquivo aleatório: impede colisão e evita expor o nome original
     * (que fica registrado em document_name) no caminho em disco.
     */
    private function storeDocument(TemporaryUploadedFile $file): string
    {
        $extension = mb_strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');

        return $file->storeAs(
            'transactions/'.Carbon::now()->format('Y/m'),
            Str::uuid7()->toString().'.'.$extension,
            'local',
        );
    }
}
