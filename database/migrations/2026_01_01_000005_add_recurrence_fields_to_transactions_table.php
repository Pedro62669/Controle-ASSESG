<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            // Todos nulos fora das saídas classificadas como recorrentes.
            $table->string('recurrence_interval', 20)->nullable()->after('expense_classification');
            $table->string('recurrence_duration', 20)->nullable()->after('recurrence_interval');

            // Número de parcelas quando a recorrência tem fim conhecido.
            $table->unsignedSmallInteger('recurrence_count')->nullable()->after('recurrence_duration');

            // Meses (1–12) escolhidos quando o intervalo é "meses específicos".
            $table->json('recurrence_months')->nullable()->after('recurrence_count');

            $table->index('recurrence_interval');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropIndex(['recurrence_interval']);
            $table->dropColumn([
                'recurrence_interval',
                'recurrence_duration',
                'recurrence_count',
                'recurrence_months',
            ]);
        });
    }
};
