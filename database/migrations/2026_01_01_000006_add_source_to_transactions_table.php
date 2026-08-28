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
            // Nullable na criação para permitir o preenchimento dos registros
            // já existentes; obrigatória pela validação a partir de agora.
            $table->string('source', 40)->nullable()->after('type');

            $table->index(['type', 'source']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropIndex(['type', 'source']);
            $table->dropColumn('source');
        });
    }
};
