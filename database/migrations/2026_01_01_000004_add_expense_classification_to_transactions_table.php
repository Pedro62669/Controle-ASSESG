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
            // Nulo para entradas: a classificação só se aplica a saídas.
            $table->string('expense_classification', 20)->nullable()->after('type');

            $table->index(['type', 'expense_classification']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropIndex(['type', 'expense_classification']);
            $table->dropColumn('expense_classification');
        });
    }
};
