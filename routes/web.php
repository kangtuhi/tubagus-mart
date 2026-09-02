<?php

use App\Http\Controllers\Admin\AccountingPeriodController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/tes', function () {
    return view('buat-tes.index');
});

Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->name('login.store');
Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'active', 'permission:dashboard.view'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/dashboard', function () {
            return view('admin.dashboard', [
                'currentUser' => request()->user(),
                'section' => 'Command Center',
            ]);
        })->name('dashboard');

        Route::middleware('permission:accounting.period.view')
            ->prefix('accounting/periods')
            ->name('accounting.periods.')
            ->group(function (): void {
                Route::get('/', [AccountingPeriodController::class, 'index'])->name('index');
                Route::get('/{period}/gate', [AccountingPeriodController::class, 'gate'])->name('gate');
            });

        Route::post('/accounting/periods/{period}/close', [AccountingPeriodController::class, 'close'])
            ->middleware('permission:accounting.period.close')
            ->name('accounting.periods.close');

        Route::post('/accounting/periods/{period}/reopen', [AccountingPeriodController::class, 'reopen'])
            ->middleware('permission:accounting.period.reopen')
            ->name('accounting.periods.reopen');
    });
