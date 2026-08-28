<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Classificação e recorrência deixam de ser exclusivas das saídas: uma
     * entrada também pode ser recorrente (auxílio mensal, verba anual).
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropIndex(['type', 'expense_classification']);
            $table->renameColumn('expense_classification', 'classification');
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->index(['type', 'classification']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropIndex(['type', 'classification']);
            $table->renameColumn('classification', 'expense_classification');
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->index(['type', 'expense_classification']);
        });
    }
};
