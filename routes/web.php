<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ListeningArchiveController;
use App\Http\Controllers\SpotifyAuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('private')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/imports', [DashboardController::class, 'imports'])->name('imports.index');
    Route::get('/library', [DashboardController::class, 'library'])->name('library.index');
    Route::get('/playlists', [DashboardController::class, 'playlists'])->name('playlists.index');
    Route::get('/listening', [ListeningArchiveController::class, 'index'])->name('listening.index');
    Route::get('/recommendations', [DashboardController::class, 'recommendations'])->name('recommendations.index');
    Route::get('/catalogue', [DashboardController::class, 'catalogue'])->name('catalogue.index');
    Route::post('/catalogue/discover', [DashboardController::class, 'discoverRecommendations'])->name('catalogue.discover');
    Route::post('/catalogue/discography', [DashboardController::class, 'discographyRecommendations'])->name('catalogue.discography');
    Route::post('/imports/lastfm', [ImportController::class, 'lastFm'])->name('imports.lastfm');
    Route::post('/imports/lastfm/full', [ImportController::class, 'fullLastFm'])->name('imports.lastfm.full');
    Route::post('/imports/lastfm/{importRun}/retry', [ImportController::class, 'retryLastFm'])->name('imports.lastfm.retry');
    Route::post('/imports/spotify/albums', [ImportController::class, 'spotifyAlbums'])->name('imports.spotify.albums');
    Route::post('/imports/spotify/liked-tracks', [ImportController::class, 'spotifyLikedTracks'])->name('imports.spotify.liked-tracks');
    Route::post('/imports/spotify/playlists', [ImportController::class, 'spotifyPlaylists'])->name('imports.spotify.playlists');
    Route::post('/imports/spotify/playlists/{playlist}/retry', [ImportController::class, 'retrySpotifyPlaylist'])->name('imports.spotify.playlists.retry');
    Route::post('/albums/{album}/completion/complete', [DashboardController::class, 'markAlbumComplete'])->name('albums.completion.complete');
    Route::post('/albums/{album}/completion/incomplete', [DashboardController::class, 'markAlbumIncomplete'])->name('albums.completion.incomplete');
    Route::post('/albums/{album}/completion/clear', [DashboardController::class, 'clearAlbumCompletionOverride'])->name('albums.completion.clear');
    Route::post('/artists/{artist}/genres', [DashboardController::class, 'updateArtistGenres'])->name('artists.genres.update');
    Route::post('/recommendations/generate', [DashboardController::class, 'generateRecommendations'])->name('recommendations.generate');
    Route::post('/recommendations/create', [DashboardController::class, 'createManualRecommendations'])->name('recommendations.create');
    Route::post('/recommendations/{recommendationRun}/albums', [DashboardController::class, 'addAlbumsToRecommendations'])->name('recommendations.albums.add');
    Route::post('/recommendations/{recommendationRun}/albums/{albumRecommendation}/remove', [DashboardController::class, 'removeAlbumFromRecommendations'])->name('recommendations.albums.remove');
    Route::get('/recommendations/{recommendationRun}/review', [DashboardController::class, 'recommendationReview'])->name('recommendations.review.page');
    Route::get('/recommendations/audit', [DashboardController::class, 'playlistAudit'])->name('recommendations.audit');
    Route::post('/recommendations/{recommendationRun}/sync', [DashboardController::class, 'syncRecommendations'])->name('recommendations.sync');
    Route::post('/recommendations/{recommendationRun}/review', [DashboardController::class, 'reviewRecommendations'])->name('recommendations.review');
    Route::get('/auth/spotify', [SpotifyAuthController::class, 'redirect'])->name('auth.spotify');
    Route::get('/auth/spotify/callback', [SpotifyAuthController::class, 'callback'])->name('auth.spotify.callback');
    Route::post('/auth/spotify/disconnect', [SpotifyAuthController::class, 'disconnect'])->name('auth.spotify.disconnect');
});
