<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SpotifyAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::post('/imports/lastfm', [DashboardController::class, 'import'])->name('imports.lastfm');
Route::post('/imports/lastfm/full', [DashboardController::class, 'fullImport'])->name('imports.lastfm.full');
Route::post('/imports/lastfm/{importRun}/retry', [DashboardController::class, 'retry'])->name('imports.lastfm.retry');
Route::post('/imports/spotify/albums', [DashboardController::class, 'spotifyAlbumsImport'])->name('imports.spotify.albums');
Route::post('/imports/spotify/playlists', [DashboardController::class, 'spotifyPlaylistsImport'])->name('imports.spotify.playlists');
Route::post('/albums/{album}/completion/complete', [DashboardController::class, 'markAlbumComplete'])->name('albums.completion.complete');
Route::post('/albums/{album}/completion/incomplete', [DashboardController::class, 'markAlbumIncomplete'])->name('albums.completion.incomplete');
Route::post('/albums/{album}/completion/clear', [DashboardController::class, 'clearAlbumCompletionOverride'])->name('albums.completion.clear');
Route::post('/recommendations/generate', [DashboardController::class, 'generateRecommendations'])->name('recommendations.generate');
Route::post('/recommendations/{recommendationRun}/sync', [DashboardController::class, 'syncRecommendations'])->name('recommendations.sync');
Route::post('/recommendations/{recommendationRun}/review', [DashboardController::class, 'reviewRecommendations'])->name('recommendations.review');
Route::get('/auth/spotify', [SpotifyAuthController::class, 'redirect'])->name('auth.spotify');
Route::get('/auth/spotify/callback', [SpotifyAuthController::class, 'callback'])->name('auth.spotify.callback');
Route::post('/auth/spotify/disconnect', [SpotifyAuthController::class, 'disconnect'])->name('auth.spotify.disconnect');
