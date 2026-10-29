<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::post('/imports/lastfm', [DashboardController::class, 'import'])->name('imports.lastfm');
