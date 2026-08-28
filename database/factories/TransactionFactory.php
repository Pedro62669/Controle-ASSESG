<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecurrenceDuration;
use App\Enums\RecurrenceInterval;
use App\Enums\TransactionClassification;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(TransactionType::cases());

        // Despesa nasce pontual: uma recorrente exige configuração de ciclo,
        // e a factory não deve produzir registro que a validação recusaria.
        return [
            'user_id' => User::factory(),
            'type' => $type,
            'source' => fake()->randomElement(TransactionSource::casesFor($type)),
            'classification' => TransactionClassification::OneOff,
            'amount' => fake()->randomFloat(2, 50, 5000),
            'transaction_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'description' => fake()->sentence(12),
            'document_path' => null,
            'document_name' => null,
        ];
    }

    public function income(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => TransactionType::Income,
            'source' => fake()->randomElement(TransactionSource::casesFor(TransactionType::Income)),
            'classification' => TransactionClassification::OneOff,
        ]);
    }

    public function expense(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => TransactionType::Expense,
            'source' => fake()->randomElement(TransactionSource::casesFor(TransactionType::Expense)),
            'classification' => TransactionClassification::OneOff,
        ]);
    }

    /**
     * Despesa recorrente já com um ciclo válido configurado.
     */
    public function recurring(
        RecurrenceInterval $interval = RecurrenceInterval::Monthly,
        RecurrenceDuration $duration = RecurrenceDuration::Indeterminate,
        ?int $count = null,
    ): static {
        return $this->state(fn (array $attributes): array => [
            'type' => TransactionType::Expense,
            'source' => fake()->randomElement(TransactionSource::casesFor(TransactionType::Expense)),
            'classification' => TransactionClassification::Recurring,
            'recurrence_interval' => $interval,
            'recurrence_duration' => $duration,
            'recurrence_count' => $duration === RecurrenceDuration::Installments ? ($count ?? 12) : null,
            'recurrence_months' => $interval === RecurrenceInterval::SpecificMonths ? [3, 9] : null,
        ]);
    }
}
