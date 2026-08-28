<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecurrenceDuration;
use App\Enums\RecurrenceInterval;
use App\Enums\TransactionClassification;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Observers\TransactionObserver;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

#[ObservedBy(TransactionObserver::class)]
#[Fillable([
    'user_id',
    'type',
    'source',
    'classification',
    'recurrence_interval',
    'recurrence_duration',
    'recurrence_count',
    'recurrence_months',
    'amount',
    'transaction_date',
    'description',
    'document_path',
    'document_name',
])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'source' => TransactionSource::class,
            'classification' => TransactionClassification::class,
            'recurrence_interval' => RecurrenceInterval::class,
            'recurrence_duration' => RecurrenceDuration::class,
            'recurrence_count' => 'integer',
            'recurrence_months' => 'array',
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Valor com sinal aplicado — base do cálculo de saldo.
     *
     * @return Attribute<float, never>
     */
    protected function signedAmount(): Attribute
    {
        return Attribute::get(
            fn (): float => (float) $this->amount * $this->type->signal(),
        );
    }

    /**
     * @return Attribute<string, never>
     */
    protected function formattedAmount(): Attribute
    {
        return Attribute::get(
            fn (): string => 'R$ '.number_format((float) $this->amount, 2, ',', '.'),
        );
    }

    /**
     * @return Attribute<bool, never>
     */
    protected function hasDocument(): Attribute
    {
        return Attribute::get(
            fn (): bool => filled($this->document_path),
        );
    }

    public function isRecurring(): bool
    {
        return $this->classification === TransactionClassification::Recurring;
    }

    /**
     * Resumo legível da recorrência, como "Mensal · 12 parcelas · até 08/2027".
     */
    public function recurrenceSummary(): ?string
    {
        if (! $this->isRecurring() || $this->recurrence_interval === null) {
            return null;
        }

        $parts = [$this->recurrence_interval->label()];

        if ($this->recurrence_interval->requiresMonthSelection()) {
            $parts[] = $this->selectedMonthNames();
        }

        if ($this->recurrence_duration === RecurrenceDuration::Installments && $this->recurrence_count !== null) {
            $parts[] = $this->recurrence_count.'x';

            if (($end = $this->recurrenceEndsAt()) !== null) {
                $parts[] = 'até '.$end->format('m/Y');
            }
        } else {
            $parts[] = 'sem prazo definido';
        }

        return implode(' · ', array_filter($parts));
    }

    /**
     * Nomes abreviados dos meses escolhidos, na ordem do calendário.
     */
    public function selectedMonthNames(): ?string
    {
        $months = $this->recurrence_months;

        if (! is_array($months) || $months === []) {
            return null;
        }

        $months = array_values(array_unique(array_map('intval', $months)));
        sort($months);

        return implode(', ', array_map(
            static fn (int $month): string => mb_convert_case(
                Carbon::create(2026, $month, 1)->translatedFormat('M'),
                MB_CASE_TITLE,
            ),
            $months,
        ));
    }

    /**
     * Data da última ocorrência, quando a recorrência tem fim conhecido.
     */
    public function recurrenceEndsAt(): ?Carbon
    {
        if ($this->recurrence_duration !== RecurrenceDuration::Installments) {
            return null;
        }

        $schedule = $this->occurrences();

        return $schedule === [] ? null : end($schedule);
    }

    /**
     * Datas em que a despesa se repete, a partir do lançamento original.
     *
     * Para recorrências sem prazo, devolve apenas as próximas $limit datas —
     * o suficiente para o usuário conferir o cronograma na tela.
     *
     * @return list<Carbon>
     */
    public function occurrences(int $limit = 24): array
    {
        if (! $this->isRecurring() || $this->recurrence_interval === null || $this->transaction_date === null) {
            return [];
        }

        $total = $this->recurrence_duration === RecurrenceDuration::Installments
            ? min((int) $this->recurrence_count, $limit)
            : $limit;

        if ($total < 1) {
            return [];
        }

        return $this->recurrence_interval->requiresMonthSelection()
            ? $this->occurrencesForSelectedMonths($total)
            : $this->occurrencesForFixedStep($total);
    }

    /**
     * @return list<Carbon>
     */
    private function occurrencesForFixedStep(int $total): array
    {
        $step = $this->recurrence_interval?->monthStep() ?? 1;
        $start = $this->transaction_date->copy();

        $dates = [];

        for ($i = 0; $i < $total; $i++) {
            // addMonthsNoOverflow evita que o dia 31 escorregue para o mês seguinte.
            $dates[] = $start->copy()->addMonthsNoOverflow($step * $i);
        }

        return $dates;
    }

    /**
     * @return list<Carbon>
     */
    private function occurrencesForSelectedMonths(int $total): array
    {
        $months = array_map('intval', $this->recurrence_months ?? []);
        sort($months);

        if ($months === []) {
            return [];
        }

        $start = $this->transaction_date->copy();
        $day = $start->day;
        $year = $start->year;

        $dates = [];

        // Percorre ano a ano até completar as ocorrências pedidas; o teto de
        // anos evita laço infinito caso a lista de meses fique inconsistente.
        for ($offset = 0; $offset < 30 && count($dates) < $total; $offset++) {
            foreach ($months as $month) {
                $candidate = Carbon::create($year + $offset, $month, 1)->startOfDay();
                $candidate->day = min($day, $candidate->daysInMonth);

                if ($candidate->lessThan($start)) {
                    continue;
                }

                $dates[] = $candidate;

                if (count($dates) >= $total) {
                    break;
                }
            }
        }

        return $dates;
    }

    public function documentUrl(): ?string
    {
        if (blank($this->document_path)) {
            return null;
        }

        return route('transactions.document', $this);
    }

    /**
     * Remove o anexo do disco. Usado ao substituir ou excluir definitivamente.
     */
    public function deleteDocument(): void
    {
        if (filled($this->document_path)) {
            Storage::disk('local')->delete($this->document_path);
        }
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOfClassification(Builder $query, TransactionClassification $classification): void
    {
        $query->where('classification', $classification);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOfType(Builder $query, TransactionType $type): void
    {
        $query->where('type', $type);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeBetweenDates(Builder $query, Carbon $start, Carbon $end): void
    {
        $query->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()]);
    }
}
