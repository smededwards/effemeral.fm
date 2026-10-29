<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::post('/imports/lastfm', [DashboardController::class, 'import'])->name('imports.lastfm');
Route::post('/imports/lastfm/full', [DashboardController::class, 'fullImport'])->name('imports.lastfm.full');
Route::post('/imports/lastfm/{importRun}/retry', [DashboardController::class, 'retry'])->name('imports.lastfm.retry');
