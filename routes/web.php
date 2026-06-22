<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Admin is now fully served via React frontend.
| Blade admin views & routes have been removed.
| All admin API endpoints are in routes/api.php under 'admin' prefix.
|
*/

// ── React SPA Entry Point ──
// Serve the React app's index.html for all SPA routes so client-side
// routing (react-router) handles navigation properly.
// API routes are prefixed with /api and handled separately.
Route::get('/{any?}', function () {
    return response()->file(public_path('index.html'));
})->where('any', '.*');
