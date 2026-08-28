<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RecurrenceDuration;
use App\Enums\TransactionClassification;
use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Projeta entradas e saídas futuras a partir das movimentações recorrentes
 * já lançadas.
 *
 * Nada aqui grava transação: a projeção é um cálculo sobre o que existe, e o
 * saldo real continua refletindo apenas o que foi efetivamente lançado.
 */
class CashFlowProjectionService
{
    /**
     * Horizontes oferecidos na tela.
     *
     * @var list<int>
     */
    public const array HORIZONS = [3, 6, 12, 24];

    public function __construct(private readonly CashFlowReportService $reports) {}

    /**
     * @return array{
     *     labels: list<string>,
     *     income: list<float>,
     *     expense: list<float>,
     *     balance: list<float>,
     *     totals: array{income: float, expense: float, result: float, finalBalance: float},
     *     series: int,
     *     months: int
     * }
     */
    public function project(int $months, ?Carbon $from = null): array
    {
        $months = $this->normalizeHorizon($months);
        $start = ($from ?? Carbon::today())->copy()->startOfMonth();
        $end = $start->copy()->addMonthsNoOverflow($months - 1)->endOfMonth();

        $buckets = $this->buildBuckets($start, $months);
        $series = $this->recurringSeries();

        foreach ($series as $transaction) {
            $this->accumulate($transaction, $buckets, $start, $end);
        }

        return $this->summarize($buckets, $months, $series->count());
    }

    /**
     * Última movimentação de cada série recorrente ativa.
     *
     * Uma despesa mensal lançada todo mês gera vários registros da mesma
     * série; projetar a partir de todos multiplicaria o valor futuro. A série
     * é identificada pela combinação de tipo, fonte, ciclo e descrição.
     *
     * @return Collection<int, Transaction>
     */
    public function recurringSeries(): Collection
    {
        return Transaction::query()
            ->where('classification', TransactionClassification::Recurring)
            ->whereNotNull('recurrence_interval')
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Transaction $transaction): string => $this->seriesKey($transaction))
            ->map(function (Collection $group): Transaction {
                /** @var Transaction $latest */
                $latest = $group->last();

                // Quantas ocorrências da série já foram lançadas — usado para
                // saber quantas parcelas ainda restam.
                $latest->setAttribute('series_count', $group->count());

                return $latest;
            })
            ->values();
    }

    private function seriesKey(Transaction $transaction): string
    {
        return implode('|', [
            $transaction->type->value,
            $transaction->source?->value ?? '-',
            $transaction->recurrence_interval?->value ?? '-',
            $transaction->recurrence_duration?->value ?? '-',
            mb_strtolower(trim((string) $transaction->description)),
        ]);
    }

    /**
     * Distribui as ocorrências futuras de uma série pelos meses projetados.
     *
     * @param  array<string, array{label: string, income: float, expense: float}>  $buckets
     */
    private function accumulate(Transaction $transaction, array &$buckets, Carbon $start, Carbon $end): void
    {
        $amount = (float) $transaction->amount;

        if ($amount <= 0) {
            return;
        }

        foreach ($this->futureOccurrences($transaction, $start, $end) as $date) {
            $key = $date->format('Y-m');

            if (! isset($buckets[$key])) {
                continue;
            }

            $column = $transaction->type === TransactionType::Income ? 'income' : 'expense';

            $buckets[$key][$column] += $amount;
        }
    }

    /**
     * Ocorrências da série que caem dentro da janela projetada e ainda não
     * foram lançadas.
     *
     * @return list<Carbon>
     */
    private function futureOccurrences(Transaction $transaction, Carbon $start, Carbon $end): array
    {
        $alreadyPosted = (int) ($transaction->getAttribute('series_count') ?? 1);
        $lastPosted = $transaction->transaction_date;

        if ($lastPosted === null) {
            return [];
        }

        $remaining = null;

        if ($transaction->recurrence_duration === RecurrenceDuration::Installments) {
            // Uma compra em 10x com 7 parcelas lançadas só projeta as 3 restantes.
            $remaining = max((int) $transaction->recurrence_count - $alreadyPosted, 0);

            if ($remaining === 0) {
                return [];
            }
        }

        // occurrences() conta a partir do lançamento existente; pedimos folga
        // suficiente para cobrir toda a janela e descartamos o que sobra.
        $limit = $remaining ?? ((int) $start->diffInMonths($end) + 2);

        $dates = [];

        foreach ($transaction->occurrences($limit + $alreadyPosted) as $date) {
            if ($date->lessThanOrEqualTo($lastPosted) || $date->greaterThan($end)) {
                continue;
            }

            if ($date->greaterThanOrEqualTo($start)) {
                $dates[] = $date;
            }

            if ($remaining !== null && count($dates) >= $remaining) {
                break;
            }
        }

        return $dates;
    }

    /**
     * @return array<string, array{label: string, income: float, expense: float}>
     */
    private function buildBuckets(Carbon $start, int $months): array
    {
        $buckets = [];
        $cursor = $start->copy();

        for ($i = 0; $i < $months; $i++) {
            $buckets[$cursor->format('Y-m')] = [
                'label' => mb_convert_case($cursor->translatedFormat('M/y'), MB_CASE_TITLE),
                'income' => 0.0,
                'expense' => 0.0,
            ];

            $cursor->addMonthNoOverflow();
        }

        return $buckets;
    }

    /**
     * @param  array<string, array{label: string, income: float, expense: float}>  $buckets
     * @return array{labels: list<string>, income: list<float>, expense: list<float>, balance: list<float>, totals: array{income: float, expense: float, result: float, finalBalance: float}, series: int, months: int}
     */
    private function summarize(array $buckets, int $months, int $seriesCount): array
    {
        $labels = [];
        $income = [];
        $expense = [];
        $balance = [];

        // O saldo projetado parte do caixa de hoje e acumula mês a mês.
        $running = $this->reports->balanceUntil(Carbon::today());

        $totalIncome = 0.0;
        $totalExpense = 0.0;

        foreach ($buckets as $bucket) {
            $labels[] = $bucket['label'];
            $income[] = round($bucket['income'], 2);
            $expense[] = round($bucket['expense'], 2);

            $running += $bucket['income'] - $bucket['expense'];
            $balance[] = round($running, 2);

            $totalIncome += $bucket['income'];
            $totalExpense += $bucket['expense'];
        }

        return [
            'labels' => $labels,
            'income' => $income,
            'expense' => $expense,
            'balance' => $balance,
            'totals' => [
                'income' => round($totalIncome, 2),
                'expense' => round($totalExpense, 2),
                'result' => round($totalIncome - $totalExpense, 2),
                'finalBalance' => round($running, 2),
            ],
            'series' => $seriesCount,
            'months' => $months,
        ];
    }

    private function normalizeHorizon(int $months): int
    {
        return in_array($months, self::HORIZONS, true) ? $months : 6;
    }
}
