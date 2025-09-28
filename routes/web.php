<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\UserSettingsController;

/**
 * routes/web.php
 *
 * Landing UX:
 * - "/" -> redirect to login (Jetstream)
 * - After authentication, "/dashboard" -> redirect to "/chat"
 *
 * Protected app area (auth + verified):
 * - Chat pages (index/show/send/stream)
 * - Settings pages (edit/update)
 */

// Landing: go straight to the login/register screen
Route::redirect('/', '/login');

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {

    // After login, send users to the chat instead of the default dashboard
    Route::redirect('/dashboard', '/chat')->name('dashboard');

    // Chat
    Route::get('/chat',         [ChatController::class, 'index'])->name('chat.index');    // main page
    Route::post('/chat/send',   [ChatController::class, 'send'])->name('chat.send');      // non-stream call
    Route::get('/chat/{id}',    [ChatController::class, 'show'])->name('chat.show');      // load a conversation
    Route::post('/chat/stream', [ChatController::class, 'stream'])->name('chat.stream');  // SSE streaming

    // Settings (custom instructions)
    Route::get('/settings',  [UserSettingsController::class, 'edit'])->name('settings.edit');
    Route::post('/settings', [UserSettingsController::class, 'update'])->name('settings.update');

    // Rename + Delete conversations
    Route::patch('/chat/{conversation}/title', [ChatController::class, 'rename'])->name('chat.rename');
    Route::delete('/chat/{conversation}', [ChatController::class, 'destroy'])->name('chat.destroy');

});
