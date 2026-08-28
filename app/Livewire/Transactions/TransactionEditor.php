<?php

declare(strict_types=1);

namespace App\Livewire\Transactions;

use App\Enums\RecurrenceDuration;
use App\Enums\RecurrenceInterval;
use App\Enums\TransactionClassification;
use App\Enums\TransactionType;
use App\Livewire\Forms\TransactionForm;
use App\Models\Transaction;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Modal de registro/edição de movimentação, com upload de comprovante.
 */
class TransactionEditor extends Component
{
    use WithFileUploads;

    /**
     * Quantas ocorrências futuras aparecem na prévia do cronograma.
     */
    private const int SCHEDULE_PREVIEW_SIZE = 6;

    public TransactionForm $form;

    public bool $open = false;

    public function mount(): void
    {
        $this->form->setDefaults();
    }

    #[On('open-transaction-editor')]
    public function openEditor(?int $transactionId = null): void
    {
        $this->form->resetForm();

        if ($transactionId !== null) {
            $transaction = Transaction::query()->findOrFail($transactionId);

            $this->authorizeEdit($transaction);
            $this->form->setTransaction($transaction);
        }

        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->form->resetForm();
    }

    /**
     * Trocar para entrada descarta a classificação, que só existe em saídas.
     */
    public function updatedFormType(): void
    {
        $this->form->syncClassificationWithType();
        $this->resetErrorBag('form.classification');
    }

    /**
     * Deixar de ser recorrente descarta toda a configuração de recorrência.
     */
    public function updatedFormClassification(): void
    {
        $this->form->syncRecurrenceWithSelection();
        $this->resetRecurrenceErrors();
    }

    public function updatedFormRecurrenceInterval(): void
    {
        $this->form->syncRecurrenceWithSelection();
        $this->resetRecurrenceErrors();
    }

    public function updatedFormRecurrenceDuration(): void
    {
        $this->form->syncRecurrenceWithSelection();
        $this->resetRecurrenceErrors();
    }

    /**
     * Marca ou desmarca um dos meses de ocorrência.
     */
    public function toggleRecurrenceMonth(int $month): void
    {
        $this->form->toggleMonth($month);
        $this->resetErrorBag('form.recurrence_months');
    }

    /**
     * Validação em tempo real: a descrição deixa de ser obrigatória assim que
     * um comprovante é anexado, e volta a ser exigida se ele for removido.
     */
    public function updatedFormDocumentPath(): void
    {
        $this->validateOnly('form.document_path');

        $this->form->removeDocument = false;
        $this->resetErrorBag('form.description');
    }

    public function updatedFormDescription(): void
    {
        $this->validateOnly('form.description');
    }

    public function removeStoredDocument(): void
    {
        $this->form->removeDocument = true;
        $this->form->document_path = null;
    }

    public function clearUpload(): void
    {
        $this->form->document_path = null;
        $this->resetErrorBag('form.document_path');
    }

    public function save(): void
    {
        $user = Auth::user();

        if ($user === null || $user->is_active !== true) {
            throw ValidationException::withMessages([
                'form.type' => 'Sua conta não está ativa para registrar movimentações.',
            ]);
        }

        if ($this->form->isEditing()) {
            $this->authorizeEdit(Transaction::query()->findOrFail($this->form->transactionId));
        }

        $wasEditing = $this->form->isEditing();

        $this->form->save($user);

        $this->open = false;
        $this->form->resetForm();

        $this->dispatch('transaction-saved');
        $this->dispatch('notify',
            type: 'success',
            message: $wasEditing ? 'Movimentação atualizada com sucesso.' : 'Movimentação registrada com sucesso.',
        );
    }

    public function render(): View
    {
        $preview = $this->previewTransaction();
        $schedule = $preview?->occurrences(self::SCHEDULE_PREVIEW_SIZE) ?? [];

        return view('livewire.transactions.transaction-editor', [
            'types' => TransactionType::cases(),
            'classifications' => TransactionClassification::cases(),
            'sources' => $this->form->availableSources(),
            'intervals' => RecurrenceInterval::cases(),
            'durations' => RecurrenceDuration::cases(),
            'months' => $this->monthNames(),
            'schedule' => array_map(
                static fn (Carbon $date): string => $date->format('d/m/Y'),
                $schedule,
            ),
            'scheduleTitle' => $preview?->recurrence_duration === RecurrenceDuration::Installments
                ? 'Cronograma das parcelas'
                : 'Próximas ocorrências',
            'schedulePreview' => $this->schedulePreview($preview),
        ]);
    }

    /**
     * Monta uma transação não persistida apenas para calcular o cronograma,
     * reaproveitando exatamente a mesma lógica usada nos registros salvos.
     */
    private function previewTransaction(): ?Transaction
    {
        if (! $this->form->isRecurring() || blank($this->form->transaction_date)) {
            return null;
        }

        $interval = $this->form->recurrenceIntervalEnum();

        if ($interval === null) {
            return null;
        }

        if ($interval->requiresMonthSelection() && $this->form->sortedMonths() === []) {
            return null;
        }

        $duration = $this->form->recurrenceDurationEnum();

        if ($duration === RecurrenceDuration::Installments && ($this->form->recurrence_count ?? 0) < 2) {
            return null;
        }

        try {
            $date = Carbon::parse($this->form->transaction_date);
        } catch (\Throwable) {
            return null;
        }

        $transaction = new Transaction;

        $transaction->type = TransactionType::tryFrom($this->form->type) ?? TransactionType::Expense;
        $transaction->classification = TransactionClassification::Recurring;
        $transaction->transaction_date = $date;
        $transaction->recurrence_interval = $interval;
        $transaction->recurrence_duration = $duration;
        $transaction->recurrence_count = $this->form->recurrence_count;
        $transaction->recurrence_months = $this->form->sortedMonths();

        return $transaction;
    }

    private function schedulePreview(?Transaction $preview): ?string
    {
        if ($preview === null) {
            return null;
        }

        $summary = $preview->recurrenceSummary();

        if ($preview->recurrence_duration !== RecurrenceDuration::Installments) {
            return $summary;
        }

        $amount = $this->form->normalizedAmount();

        if ($amount <= 0) {
            return $summary;
        }

        return sprintf(
            '%dx de R$ %s · %s',
            (int) $preview->recurrence_count,
            number_format($amount, 2, ',', '.'),
            $summary,
        );
    }

    /**
     * @return array<int, string>
     */
    private function monthNames(): array
    {
        $names = [];

        for ($month = 1; $month <= 12; $month++) {
            $names[$month] = mb_convert_case(
                Carbon::create(2026, $month, 1)->translatedFormat('M'),
                MB_CASE_TITLE,
            );
        }

        return $names;
    }

    private function resetRecurrenceErrors(): void
    {
        $this->resetErrorBag([
            'form.recurrence_interval',
            'form.recurrence_duration',
            'form.recurrence_count',
            'form.recurrence_months',
        ]);
    }

    /**
     * Autor da movimentação ou administrador principal — mesma regra do
     * UpdateTransactionRequest.
     */
    private function authorizeEdit(Transaction $transaction): void
    {
        $user = Auth::user();

        abort_unless(
            $user !== null && ($user->isMainAdmin() || $transaction->user_id === $user->getKey()),
            403,
            'Você não tem permissão para editar esta movimentação.',
        );
    }
}
