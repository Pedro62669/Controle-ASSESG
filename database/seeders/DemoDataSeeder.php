<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RecurrenceDuration;
use App\Enums\RecurrenceInterval;
use App\Enums\TransactionClassification;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Popula a base com movimentações fictícias para demonstração.
 *
 * Cada lançamento é criado com o respectivo responsável autenticado, de modo
 * que a trilha em system_logs fique tão realista quanto o fluxo de caixa.
 */
class DemoDataSeeder extends Seeder
{
    /**
     * Entradas: [descrição, mínimo, máximo, fonte, classificação, intervalo, duração, parcelas, meses].
     *
     * @var list<array{0: string, 1: int, 2: int, 3: string, 4: string, 5: ?string, 6: ?string, 7: ?int, 8: ?list<int>}>
     */
    private const array INCOMES = [
        ['Repasse do convênio municipal de assistência social referente ao mês', 8000, 8000, 'public_agreement', 'recurring', 'monthly', 'indeterminate', null, null],
        ['Auxílio federal do programa de fortalecimento de vínculos', 2600, 2600, 'public_agreement', 'recurring', 'monthly', 'indeterminate', null, null],
        ['Contribuição mensal dos associados arrecadada na secretaria', 3200, 5400, 'member_contribution', 'recurring', 'monthly', 'indeterminate', null, null],
        ['Doação semestral da empresa madrinha do projeto', 7500, 7500, 'company_donation', 'recurring', 'semiannual', 'indeterminate', null, null],
        ['Verba anual da campanha de nota fiscal solidária', 12000, 12000, 'public_agreement', 'recurring', 'annual', 'indeterminate', null, null],
        ['Doação de pessoa física para o programa de apoio às famílias', 500, 2500, 'individual_donation', 'one_off', null, null, null, null],
        ['Arrecadação do bazar beneficente realizado no salão da sede', 900, 2800, 'solidarity_sale', 'one_off', null, null, null, null],
        ['Rendimento da aplicação financeira da reserva da associação', 180, 640, 'financial_income', 'recurring', 'monthly', 'indeterminate', null, null],
    ];

    /**
     * Saídas: [descrição, mínimo, máximo, fonte, classificação, intervalo, duração, parcelas, meses].
     *
     * @var list<array{0: string, 1: int, 2: int, 3: string, 4: string, 5: ?string, 6: ?string, 7: ?int, 8: ?list<int>}>
     */
    private const array EXPENSES = [
        ['Aluguel da sede administrativa da associação', 2200, 2200, 'rent', 'recurring', 'monthly', 'indeterminate', null, null],
        ['Conta de energia elétrica da sede', 380, 720, 'utilities', 'recurring', 'monthly', 'indeterminate', null, null],
        ['Conta de água e esgoto da sede', 140, 260, 'utilities', 'recurring', 'monthly', 'indeterminate', null, null],
        ['Compra de alimentos para montagem das cestas básicas', 1800, 3600, 'food_supplies', 'recurring', 'monthly', 'indeterminate', null, null],
        ['Material de limpeza e higiene para as oficinas', 260, 680, 'consumable_supplies', 'one_off', null, null, null, null],
        ['Combustível do veículo utilizado nas entregas', 300, 620, 'transport', 'one_off', null, null, null, null],
        ['Material didático das oficinas socioeducativas', 400, 1100, 'consumable_supplies', 'one_off', null, null, null, null],
        ['Serviço de contabilidade da associação', 890, 890, 'outsourced_services', 'recurring', 'monthly', 'indeterminate', null, null],
        ['Parcelas do computador da secretaria adquirido em 10 vezes', 389, 389, 'equipment', 'recurring', 'monthly', 'installments', 10, null],
        ['Renovação do seguro predial da sede', 1450, 1450, 'taxes_and_fees', 'recurring', 'annual', 'indeterminate', null, null],
        ['Manutenção preventiva do ar-condicionado das salas', 640, 640, 'maintenance', 'recurring', 'semiannual', 'indeterminate', null, null],
        ['Campanha do agasalho: compra de cobertores', 1900, 2600, 'food_supplies', 'recurring', 'specific_months', 'indeterminate', null, [5, 6]],
        ['Folha de pagamento da equipe técnica', 4200, 4200, 'payroll', 'recurring', 'monthly', 'indeterminate', null, null],
    ];

    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@assesg.org.br'],
            [
                'name' => 'Administrador ASSESG',
                'password' => Hash::make('assesg@2026'),
                'is_main_admin' => true,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $team = $this->team();
        $responsibles = [$admin, ...array_values($team)];

        $start = Carbon::create(2026, 1, 1);
        $today = Carbon::today();

        // Semente fixa: rodar o seeder de novo produz os mesmos números.
        mt_srand(2026);

        $month = $start->copy();

        while ($month->lessThanOrEqualTo($today)) {
            $this->seedMonth($month, $responsibles, $today);

            $month->addMonthNoOverflow();
        }

        Auth::logout();

        $this->command?->info(sprintf(
            'Demonstração criada: %d movimentações e %d usuários.',
            Transaction::query()->count(),
            User::query()->count(),
        ));
    }

    /**
     * @return array<string, User>
     */
    private function team(): array
    {
        $members = [
            'tesouraria' => ['Marina Alves Ribeiro', 'tesouraria@assesg.org.br'],
            'secretaria' => ['Paulo Henrique Torres', 'secretaria@assesg.org.br'],
            'projetos' => ['Cláudia Ferreira Lima', 'projetos@assesg.org.br'],
        ];

        $team = [];

        foreach ($members as $key => [$name, $email]) {
            $team[$key] = User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('assesg@2026'),
                    'is_main_admin' => false,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );
        }

        return $team;
    }

    /**
     * @param  list<User>  $responsibles
     */
    private function seedMonth(Carbon $month, array $responsibles, Carbon $today): void
    {
        foreach (self::INCOMES as $index => [$description, $min, $max, $source, $classification, $interval, $duration, $count, $months]) {
            if (! $this->occursInMonth($month, $interval, $months)) {
                continue;
            }

            // Doações avulsas são eventuais; as recorrentes entram sempre.
            if ($classification === 'one_off' && mt_rand(1, 100) > 55) {
                continue;
            }

            $this->createTransaction(
                type: TransactionType::Income,
                description: $description,
                amount: $this->amountBetween($min, $max),
                date: $this->dateWithin($month, $today, $index * 3 + 2),
                responsible: $responsibles[array_rand($responsibles)],
                source: TransactionSource::from($source),
                classification: TransactionClassification::from($classification),
                interval: $interval === null ? null : RecurrenceInterval::from($interval),
                duration: $duration === null ? null : RecurrenceDuration::from($duration),
                count: $count,
                months: $months,
            );
        }

        foreach (self::EXPENSES as $index => [$description, $min, $max, $source, $classification, $interval, $duration, $count, $months]) {
            // Despesas de intervalo largo só entram nos meses em que ocorrem.
            if (! $this->occursInMonth($month, $interval, $months)) {
                continue;
            }

            if ($index > 2 && $index < 8 && mt_rand(1, 100) > 70) {
                continue;
            }

            $this->createTransaction(
                type: TransactionType::Expense,
                description: $description,
                amount: $this->amountBetween($min, $max),
                date: $this->dateWithin($month, $today, $index * 3 + 5),
                responsible: $responsibles[array_rand($responsibles)],
                source: TransactionSource::from($source),
                classification: TransactionClassification::from($classification),
                interval: $interval === null ? null : RecurrenceInterval::from($interval),
                duration: $duration === null ? null : RecurrenceDuration::from($duration),
                count: $count,
                months: $months,
            );
        }
    }

    private function createTransaction(
        TransactionType $type,
        string $description,
        float $amount,
        ?Carbon $date,
        User $responsible,
        ?TransactionSource $source = null,
        ?TransactionClassification $classification = null,
        ?RecurrenceInterval $interval = null,
        ?RecurrenceDuration $duration = null,
        ?int $count = null,
        ?array $months = null,
    ): void {
        if ($date === null) {
            return;
        }

        // Autentica o responsável para que o observer registre a autoria real.
        Auth::login($responsible);

        // Parte dos lançamentos vai com comprovante anexado; o restante fica
        // apenas com a justificativa, exercitando as duas metades da regra.
        $withDocument = mt_rand(1, 100) <= 45;

        $documentPath = null;
        $documentName = null;

        if ($withDocument) {
            [$documentPath, $documentName] = $this->createReceipt($type, $description, $amount, $date);
        }

        Transaction::query()->create([
            'user_id' => $responsible->getKey(),
            'type' => $type,
            'source' => $source,
            'classification' => $classification,
            'recurrence_interval' => $interval,
            'recurrence_duration' => $duration,
            'recurrence_count' => $count,
            'recurrence_months' => $months,
            'amount' => $amount,
            'transaction_date' => $date,
            'description' => $description,
            'document_path' => $documentPath,
            'document_name' => $documentName,
        ]);
    }

    /**
     * Gera um comprovante PNG legível, para que a rota de download entregue
     * um arquivo de verdade em vez de um caminho quebrado.
     *
     * @return array{0: string, 1: string}
     */
    private function createReceipt(
        TransactionType $type,
        string $description,
        float $amount,
        Carbon $date,
    ): array {
        $image = imagecreatetruecolor(760, 320);

        $white = imagecolorallocate($image, 255, 255, 255);
        $navy = imagecolorallocate($image, 11, 58, 93);
        $sage = imagecolorallocate($image, 98, 139, 114);
        $sand = imagecolorallocate($image, 201, 185, 153);

        imagefill($image, 0, 0, $white);
        imagefilledrectangle($image, 0, 0, 760, 8, $navy);
        imagefilledrectangle($image, 0, 300, 760, 320, $sand);

        imagestring($image, 5, 30, 40, 'ASSESG - COMPROVANTE DE MOVIMENTACAO', $navy);
        imagestring($image, 3, 30, 90, 'Tipo: '.($type === TransactionType::Income ? 'ENTRADA' : 'SAIDA'), $sage);
        imagestring($image, 3, 30, 120, 'Data: '.$date->format('d/m/Y'), $navy);
        imagestring($image, 4, 30, 150, 'Valor: R$ '.number_format($amount, 2, ',', '.'), $navy);
        imagestring($image, 2, 30, 195, substr(Str::ascii($description), 0, 90), $navy);
        imagestring($image, 1, 30, 250, 'Documento gerado para fins de demonstracao do sistema.', $sage);

        ob_start();
        imagepng($image);
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        $path = 'transactions/'.$date->format('Y/m').'/'.Str::uuid7()->toString().'.png';

        Storage::disk('local')->put($path, $contents);

        return [$path, 'comprovante-'.$date->format('Y-m-d').'.png'];
    }

    /**
     * Uma despesa anual, semestral ou de meses fixos não aparece todo mês.
     *
     * @param  list<int>|null  $months
     */
    private function occursInMonth(Carbon $month, ?string $interval, ?array $months): bool
    {
        return match ($interval) {
            'annual' => $month->month === 3,
            'semiannual' => in_array($month->month, [2, 8], true),
            'specific_months' => in_array($month->month, $months ?? [], true),
            default => true,
        };
    }

    private function amountBetween(int $min, int $max): float
    {
        if ($min === $max) {
            return (float) $min;
        }

        return round(mt_rand($min * 100, $max * 100) / 100, 2);
    }

    /**
     * Data dentro do mês, nunca no futuro.
     */
    private function dateWithin(Carbon $month, Carbon $today, int $day): ?Carbon
    {
        $date = $month->copy()->day(min($day, $month->daysInMonth));

        return $date->greaterThan($today) ? null : $date;
    }
}
