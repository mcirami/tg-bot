<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TelegramConnectionController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\TelegramAutomationController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    //Route::get('/telegram/connect', [TelegramController::class, 'showConnectForm'])->name('telegram.connect');
    //Route::post('/telegram/connect', [TelegramController::class, 'sendCode']);

    Route::get('/telegram/connect', [TelegramConnectionController::class, 'edit'])
         ->name('telegram.connect.edit');

    Route::post('/telegram/connect/send-code', [TelegramConnectionController::class, 'sendCode'])
         ->name('telegram.connect.send-code');

    Route::post('/telegram/connect/verify-code', [TelegramConnectionController::class, 'verifyCode'])
         ->name('telegram.connect.verify-code');

    Route::post('/telegram/connect/verify-password', [TelegramConnectionController::class, 'verifyPassword'])
         ->name('telegram.connect.verify-password');

    Route::post('/telegram/connect/disconnect', [TelegramConnectionController::class, 'disconnect'])
         ->name('telegram.connect.disconnect');

    Route::post('/telegram/connect/test-status', [TelegramConnectionController::class, 'testStatus'])
         ->name('telegram.connect.test-status');

    Route::get('/telegram/automation', [TelegramAutomationController::class, 'edit'])
         ->name('telegram.automation.edit');

    Route::post('/telegram/automation', [TelegramAutomationController::class, 'update'])
         ->name('telegram.automation.update');
});

require __DIR__.'/auth.php';
