<?php

use App\Http\Controllers\BranchController;
use App\Http\Controllers\BranchDailyClosureController;
use App\Http\Controllers\DrawController;
use App\Http\Controllers\DrawResultController;
use App\Http\Controllers\PrizePayoutController;
use App\Http\Controllers\SettlementController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IncomingMessageController;
use App\Http\Controllers\IntakeRequestController;
use App\Http\Controllers\NumberBoardController;
use App\Http\Controllers\NumberLimitController;
use App\Http\Controllers\PilotPageController;
use App\Http\Controllers\SimulatorController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
    Route::get('/draws', [DrawController::class, 'index'])->name('draws.index');
    Route::get('/draws/{draw}/edit', [DrawController::class, 'edit'])->name('draws.edit');
    Route::patch('/draws/{draw}', [DrawController::class, 'update'])->name('draws.update');
    Route::get('/closures', [BranchDailyClosureController::class, 'index'])->name('closures.index');
    Route::post('/closures', [BranchDailyClosureController::class, 'store'])->name('closures.store');
    Route::get('/closures/{closure}', [BranchDailyClosureController::class, 'show'])->name('closures.show');
    Route::get('/closures/{closure}/export', [BranchDailyClosureController::class, 'export'])->name('closures.export');
    Route::get('/pilot-checklist', [PilotPageController::class, 'checklist'])->name('pilot.checklist');
    Route::get('/pilot-script', [PilotPageController::class, 'script'])->name('pilot.script');
    Route::get('/operator-guide', [PilotPageController::class, 'guide'])->name('pilot.guide');
    Route::get('/incoming-messages', [IncomingMessageController::class, 'index'])->name('incoming-messages.index');
    Route::get('/simulator', [SimulatorController::class, 'index'])->name('simulator.index');
    Route::post('/simulator', [SimulatorController::class, 'store'])->name('simulator.store');
    Route::get('/numbers', [NumberBoardController::class, 'index'])->name('numbers.index');
    Route::post('/numbers', [NumberBoardController::class, 'store'])->name('numbers.store');

    Route::prefix('/limits')->name('limits.')->group(function () {
        Route::get('/', [NumberLimitController::class, 'index'])->name('index');
        Route::get('/create', [NumberLimitController::class, 'create'])->name('create');
        Route::post('/', [NumberLimitController::class, 'store'])->name('store');
        Route::get('/{limit}/edit', [NumberLimitController::class, 'edit'])->name('edit');
        Route::put('/{limit}', [NumberLimitController::class, 'update'])->name('update');
        Route::delete('/{limit}', [NumberLimitController::class, 'destroy'])->name('delete');
    });

    Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'edit', 'update']);

    Route::get('/results', [DrawResultController::class, 'index'])->name('results.index');
    Route::post('/results', [DrawResultController::class, 'store'])->name('results.store');
    Route::post('/results/{result}/notify', [DrawResultController::class, 'notify'])->name('results.notify');
    Route::get('/payouts', [PrizePayoutController::class, 'index'])->name('payouts.index');
    Route::post('/payouts/{payout}/pay', [PrizePayoutController::class, 'pay'])->name('payouts.pay');
    Route::get('/settlement', [SettlementController::class, 'index'])->name('settlement.index');

    Route::prefix('/requests')->name('intake-requests.')->group(function () {
        Route::get('/', [IntakeRequestController::class, 'index'])->name('index');
        Route::get('/{intakeRequest}', [IntakeRequestController::class, 'show'])->name('show');
        Route::get('/{intakeRequest}/edit', [IntakeRequestController::class, 'edit'])->name('edit');
        Route::patch('/{intakeRequest}', [IntakeRequestController::class, 'update'])->name('update');
        Route::post('/{intakeRequest}/confirm', [IntakeRequestController::class, 'confirm'])->name('confirm');
        Route::post('/{intakeRequest}/reject', [IntakeRequestController::class, 'reject'])->name('reject');
        Route::post('/{intakeRequest}/clarify', [IntakeRequestController::class, 'clarify'])->name('clarify');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
