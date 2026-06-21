<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\ReconciliationExportController;
use App\Http\Controllers\HistoryLogController;

// Root → Dashboard
Route::get('/', fn() => redirect()->route('dashboard'));

// PAGE 1: Audit Dashboard & Search
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->name('dashboard');

// PAGE 2: Master Reconciliation Sheet
Route::get('/reconciliation', [ReconciliationController::class, 'index'])
    ->name('reconciliation.index');

Route::post('/units', [ReconciliationController::class, 'store'])
    ->name('units.store');

Route::put('/units/{unit}', [ReconciliationController::class, 'update'])
    ->name('units.update');

Route::delete('/units/{unit}', [ReconciliationController::class, 'destroy'])
    ->name('units.destroy');

// Export Master Reconciliation Sheet to .xlsx (respects current page filters)
Route::get('/reconciliation/export', [ReconciliationExportController::class, 'export'])
    ->name('reconciliation.export');

// PAGE 3: History Log (soft-deleted records)
Route::get('/history', [HistoryLogController::class, 'index'])
    ->name('history.index');