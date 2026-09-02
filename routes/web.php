<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/tes', function () {
    return view('buat-tes.index');
});

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
    });
