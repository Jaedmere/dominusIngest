<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\MonitorController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('monitor'));

// En vez de mostrar dashboard, lo mandamos a monitor
Route::get('/dashboard', fn () => redirect()->route('monitor'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/monitor', [MonitorController::class, 'index'])->name('monitor');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
