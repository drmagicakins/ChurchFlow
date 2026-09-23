<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// --- Phase 1: Foundation routes ---

Route::middleware('guest')->group(function () {
    Route::get('/register', fn () => view('auth.register'))->name('register');
    Route::post('/register', [RegisterController::class, 'store']);

    Route::get('/login', fn () => view('auth.login'))->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware(['auth', \App\Http\Middleware\IdentifyTenant::class])->group(function () {
    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});

Route::middleware(['auth', \App\Http\Middleware\PlatformAdminOnly::class])
    ->prefix('platform-admin')
    ->group(function () {
        Route::get('/', fn () => view('platform-admin.dashboard'))->name('platform-admin.dashboard');
    });
