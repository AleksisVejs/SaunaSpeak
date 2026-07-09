<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CheckpointController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\TtsController;
use App\Http\Controllers\WordController;
use Illuminate\Support\Facades\Route;

// Public
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Authenticated
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/preferences', [AuthController::class, 'updatePreferences']);

    Route::post('/chat', [ChatController::class, 'chat']);
    Route::post('/tts', [TtsController::class, 'speak']);

    Route::get('/lessons', [LessonController::class, 'index']);
    Route::get('/lessons/{lesson}', [LessonController::class, 'show']);

    Route::get('/today-session', [SessionController::class, 'today']);
    Route::post('/progress/complete', [SessionController::class, 'completeSentence']);
    Route::post('/session/complete', [SessionController::class, 'completeSession']);

    Route::post('/ai/correct', [AiController::class, 'correct']);

    Route::get('/checkpoint/{level}', [CheckpointController::class, 'show']);
    Route::post('/checkpoint/{level}', [CheckpointController::class, 'complete']);

    Route::get('/words', [WordController::class, 'index']);
    Route::get('/words/review', [WordController::class, 'review']);
    Route::post('/words', [WordController::class, 'store']);
    Route::post('/words/{id}/grade', [WordController::class, 'grade']);
    Route::delete('/words/{id}', [WordController::class, 'destroy']);
});
