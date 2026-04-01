<?php

use App\Http\Controllers\Central\RegisterController;
use App\Http\Controllers\Central\LoginController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {
        Route::get('/', function () {
            return Inertia::render('Central/Welcome');
        })->name('home');

        // Central auth routes (for salon owners)
        Route::middleware('guest')->group(function () {
            Route::get('/register', [RegisterController::class, 'create'])->name('register');
            Route::post('/register', [RegisterController::class, 'store']);
            Route::get('/login', [LoginController::class, 'create'])->name('login');
            Route::post('/login', [LoginController::class, 'store']);
        });

        Route::post('/logout', [LoginController::class, 'destroy'])
            ->middleware('auth:central')
            ->name('logout');
    });
}
