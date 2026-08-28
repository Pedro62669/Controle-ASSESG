<?php

declare(strict_types=1);

use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\TransactionDocumentController;
use App\Livewire\Admin\SystemLogViewer;
use App\Livewire\Admin\UserManager;
use App\Livewire\Auth\Login;
use App\Livewire\Dashboard;
use App\Livewire\Transactions\TransactionList;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', Login::class)->name('login');
});

/*
|--------------------------------------------------------------------------
| Área autenticada
|--------------------------------------------------------------------------
| Todo usuário logado e ativo registra movimentações e vê o dashboard.
*/
Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/movimentacoes', TransactionList::class)->name('transactions.index');

    Route::get('/movimentacoes/{transaction}/comprovante', TransactionDocumentController::class)
        ->name('transactions.document');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    /*
    |----------------------------------------------------------------------
    | Área exclusiva do administrador principal
    |----------------------------------------------------------------------
    | Cadastro de usuários e logs do sistema, isolados por main.admin.
    */
    Route::middleware('main.admin')->prefix('administracao')->name('admin.')->group(function (): void {
        Route::get('/usuarios', UserManager::class)->name('users');
        Route::get('/logs', SystemLogViewer::class)->name('logs');
    });
});
