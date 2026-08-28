<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_main_admin')->default(false)->after('password');
            $table->boolean('is_active')->default(true)->after('is_main_admin');
            $table->softDeletes();

            $table->index('is_main_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['is_main_admin']);
            $table->dropSoftDeletes();
            $table->dropColumn(['is_main_admin', 'is_active']);
        });
    }
};
