<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

/**
 * Consolida os números do fluxo de caixa consumidos pelo dashboard.
 *
 * Toda agregação acontece no banco (SUM/GROUP BY); o serviço apenas monta
 * os intervalos e formata o resultado para os gráficos.
 */
class CashFlowReportService
{
    /**
     * Totais do período mais o saldo acumulado em caixa até a data final.
     *
     * @return array{income: float, expense: float, result: float, balance: float, count: int}
     */
    public function summary(Carbon $start, Carbon $end): array
    {
        /** @var object{income: float|string|null, expense: float|string|null, total: int} $totals */
        $totals = Transaction::query()
            ->betweenDates($start, $end)
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN amount END), 0) AS income', [TransactionType::Income->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN amount END), 0) AS expense', [TransactionType::Expense->value])
            ->selectRaw('COUNT(*) AS total')
            ->first();

        $income = (float) ($totals->income ?? 0);
        $expense = (float) ($totals->expense ?? 0);

        return [
            'income' => $income,
            'expense' => $expense,
            'result' => $income - $expense,
            'balance' => $this->balanceUntil($end),
            'count' => (int) ($totals->total ?? 0),
        ];
    }

    /**
     * Saldo em caixa considerando todo o histórico até a data informada.
     */
    public function balanceUntil(Carbon $end): float
    {
        /** @var object{income: float|string|null, expense: float|string|null}|null $totals */
        $totals = Transaction::query()
            ->whereDate('transaction_date', '<=', $end->toDateString())
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN amount END), 0) AS income', [TransactionType::Income->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN amount END), 0) AS expense', [TransactionType::Expense->value])
            ->first();

        return (float) ($totals->income ?? 0) - (float) ($totals->expense ?? 0);
    }

    /**
     * Série temporal de entradas x saídas, agrupada em dias, semanas ou meses
     * conforme a amplitude do período — evita gráficos com centenas de barras.
     *
     * @return array{labels: list<string>, income: list<float>, expense: list<float>, granularity: string}
     */
    public function timeSeries(Carbon $start, Carbon $end): array
    {
        $granularity = $this->granularityFor($start, $end);
        $buckets = $this->buildBuckets($start, $end, $granularity);

        $rows = Transaction::query()
            ->betweenDates($start, $end)
            ->selectRaw('transaction_date, type, SUM(amount) AS total')
            ->groupBy('transaction_date', 'type')
            ->get();

        foreach ($rows as $row) {
            $date = Carbon::parse((string) $row->getAttribute('transaction_date'));
            $key = $this->bucketKey($date, $granularity);

            if (! isset($buckets[$key])) {
                continue;
            }

            $type = $row->getAttribute('type');
            $column = $type instanceof TransactionType ? $type->value : (string) $type;

            $buckets[$key][$column] += (float) $row->getAttribute('total');
        }

        return [
            'labels' => array_values(array_map(
                static fn (array $bucket): string => $bucket['label'],
                $buckets,
            )),
            'income' => array_values(array_map(
                static fn (array $bucket): float => round($bucket[TransactionType::Income->value], 2),
                $buckets,
            )),
            'expense' => array_values(array_map(
                static fn (array $bucket): float => round($bucket[TransactionType::Expense->value], 2),
                $buckets,
            )),
            'granularity' => $granularity,
        ];
    }

    /**
     * Composição de um tipo de movimentação por fonte no período —
     * a origem do dinheiro que entrou ou o destino do que saiu.
     *
     * @return array{labels: list<string>, values: list<float>}
     */
    public function breakdownBySource(TransactionType $type, Carbon $start, Carbon $end, int $limit = 7): array
    {
        $rows = Transaction::query()
            ->betweenDates($start, $end)
            ->ofType($type)
            ->whereNotNull('source')
            ->selectRaw('source, SUM(amount) AS total')
            ->groupBy('source')
            ->orderByDesc('total')
            ->get();

        $labels = [];
        $values = [];
        $others = 0.0;

        foreach ($rows->values() as $index => $row) {
            $total = round((float) $row->getAttribute('total'), 2);

            if ($index < $limit) {
                $source = $row->getAttribute('source');

                $labels[] = $source instanceof TransactionSource
                    ? $source->label()
                    : (TransactionSource::tryFrom((string) $source)?->label() ?? 'Não informada');
                $values[] = $total;

                continue;
            }

            // A cauda longa vira uma fatia só, para a pizza seguir legível.
            $others += $total;
        }

        if ($others > 0) {
            $labels[] = 'Outras fontes';
            $values[] = round($others, 2);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Composição do caixa: quanto do total que entrou permanece retido.
     *
     * @return array{labels: list<string>, values: list<float>}
     */
    public function retainedComposition(Carbon $end): array
    {
        /** @var object{income: float|string|null, expense: float|string|null}|null $totals */
        $totals = Transaction::query()
            ->whereDate('transaction_date', '<=', $end->toDateString())
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN amount END), 0) AS income', [TransactionType::Income->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN amount END), 0) AS expense', [TransactionType::Expense->value])
            ->first();

        $income = (float) ($totals->income ?? 0);
        $expense = (float) ($totals->expense ?? 0);
        $retained = max($income - $expense, 0.0);

        return [
            'labels' => ['Retido em caixa', 'Já utilizado'],
            'values' => [round($retained, 2), round(min($expense, $income), 2)],
        ];
    }

    private function granularityFor(Carbon $start, Carbon $end): string
    {
        $days = $start->diffInDays($end) + 1;

        return match (true) {
            $days <= 31 => 'day',
            $days <= 182 => 'week',
            default => 'month',
        };
    }

    /**
     * @return array<string, array{label: string, income: float, expense: float}>
     */
    private function buildBuckets(Carbon $start, Carbon $end, string $granularity): array
    {
        $buckets = [];
        $cursor = $this->normalizeCursor($start->copy(), $granularity);
        $limit = $end->copy()->endOfDay();

        while ($cursor->lessThanOrEqualTo($limit)) {
            $buckets[$this->bucketKey($cursor, $granularity)] = [
                'label' => $this->bucketLabel($cursor, $granularity),
                TransactionType::Income->value => 0.0,
                TransactionType::Expense->value => 0.0,
            ];

            $cursor = match ($granularity) {
                'day' => $cursor->addDay(),
                'week' => $cursor->addWeek(),
                default => $cursor->addMonthNoOverflow(),
            };
        }

        return $buckets;
    }

    private function normalizeCursor(Carbon $date, string $granularity): Carbon
    {
        return match ($granularity) {
            'day' => $date->startOfDay(),
            'week' => $date->startOfWeek(),
            default => $date->startOfMonth(),
        };
    }

    private function bucketKey(Carbon $date, string $granularity): string
    {
        return match ($granularity) {
            'day' => $date->format('Y-m-d'),
            'week' => $date->copy()->startOfWeek()->format('Y-m-d'),
            default => $date->format('Y-m'),
        };
    }

    private function bucketLabel(Carbon $date, string $granularity): string
    {
        return match ($granularity) {
            'day' => $date->format('d/m'),
            'week' => $date->copy()->startOfWeek()->format('d/m').' - '.$date->copy()->endOfWeek()->format('d/m'),
            default => mb_convert_case($date->translatedFormat('M/y'), MB_CASE_TITLE),
        };
    }
}
