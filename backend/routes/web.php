<?php

use Illuminate\Support\Facades\Route;

/*
 * Production serves the built Vue SPA from public/ (frontend/dist is copied
 * there at deploy time). Any non-API path falls back to the SPA's index.html
 * so client-side routes like /dashboard survive a page refresh. In local dev
 * (no index.html in public/) the Vite dev server owns the frontend instead.
 */
Route::get('/{any?}', function () {
    $spa = public_path('index.html');

    return file_exists($spa)
        ? response()->file($spa)
        : view('welcome');
})->where('any', '^(?!api|audio|images|assets).*');
