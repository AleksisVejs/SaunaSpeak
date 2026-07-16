<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CheckpointController;
use App\Http\Controllers\InsightsController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\MistakeController;
use App\Http\Controllers\PublicLessonController;
use App\Http\Controllers\RecordController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\TtsController;
use App\Http\Controllers\WordController;
use Illuminate\Support\Facades\Route;

// Public - tightly throttled: these are the brute-force targets.
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/password/reset', [AuthController::class, 'resetPassword']);
});

// Reset mails are costlier than logins (SMTP send per hit) - throttle harder.
Route::post('/password/forgot', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');

// "Continue with Google" - browser round-trips through Google, lands back on
// the callback, which redirects into the SPA with a token in the fragment.
Route::get('/auth/google/redirect', [AuthController::class, 'googleRedirect'])->middleware('throttle:10,1,oauth');
Route::get('/auth/google/callback', [AuthController::class, 'googleCallback'])->middleware('throttle:10,1,oauth');

// Public lesson previews - the /lessons SEO pages read these without auth.
// Generous throttle: crawlers and logged-out browsers share per-IP buckets.
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/public/lessons', [PublicLessonController::class, 'index']);
    Route::get('/public/lessons/{slug}', [PublicLessonController::class, 'show']);
});

// The link inside the verification mail. 'signed:relative' rejects tampering
// with id/hash/expiry but ignores scheme/host, so cPanel's https/www redirects
// can't invalidate it; the throttle caps drive-by probing on top of that.
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed:relative', 'throttle:10,1'])
    ->name('verification.verify');

// Stripe calls this, not browsers; signature check happens in the controller.
// Kept loose: Stripe redelivers bursts after an outage and a 429 there just
// means retries pile up. The signature check is what actually guards this.
Route::post('/billing/webhook', [BillingController::class, 'webhook'])->middleware('throttle:300,1');

// Authenticated
//
// Route-level throttles below carry a third parameter (a key prefix).
// Without it they'd share their hit counter with this group's throttle:120,1
// - every request would count against both limits at once, silently halving
// the route-specific ones.
Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/preferences', [AuthController::class, 'updatePreferences']);
    Route::post('/email/resend', [AuthController::class, 'resendVerification'])->middleware('throttle:3,1,resend');

    // The learner's data, both directions: export (GDPR portability) and
    // password-confirmed deletion (GDPR erasure).
    Route::get('/account/export', [AccountController::class, 'export'])->middleware('throttle:5,1,export');
    Route::delete('/account', [AccountController::class, 'destroy'])->middleware('throttle:5,1,delacct');

    Route::get('/lessons', [LessonController::class, 'index']);
    Route::get('/lessons/{lesson}', [LessonController::class, 'show']);

    Route::get('/today-session', [SessionController::class, 'today']);
    Route::post('/progress/complete', [SessionController::class, 'completeSentence']);
    Route::post('/session/complete', [SessionController::class, 'completeSession']);
    Route::post('/streak/repair', [SessionController::class, 'repairStreak'])->middleware('throttle:10,1,repair');

    Route::get('/checkpoint/{level}', [CheckpointController::class, 'show']);
    Route::post('/checkpoint/{level}', [CheckpointController::class, 'complete']);

    Route::get('/words', [WordController::class, 'index']);
    Route::get('/words/review', [WordController::class, 'review']);
    Route::post('/words', [WordController::class, 'store']);
    Route::post('/words/{id}/grade', [WordController::class, 'grade']);
    Route::delete('/words/{id}', [WordController::class, 'destroy']);

    // Chat mistakes as flashcards. Capture happens inside the premium /chat;
    // reviewing what was already captured stays open (it's the learner's own
    // data, and reviews must not die with a lapsed subscription).
    Route::get('/mistakes/review', [MistakeController::class, 'review']);
    Route::post('/mistakes/{id}/grade', [MistakeController::class, 'grade']);
    Route::delete('/mistakes/{id}', [MistakeController::class, 'destroy']);

    Route::get('/billing', [BillingController::class, 'status']);
    // Embedded checkout creates a session on every open/reopen of the form,
    // so this needs headroom; sessions are free on Stripe's side. Per-user.
    Route::post('/billing/checkout', [BillingController::class, 'checkout'])->middleware('throttle:20,1,checkout');
    Route::post('/billing/portal', [BillingController::class, 'portal'])->middleware('throttle:10,1,portal');
    Route::post('/billing/cancel', [BillingController::class, 'cancel'])->middleware('throttle:15,1,subchange');
    Route::post('/billing/resume', [BillingController::class, 'resume'])->middleware('throttle:15,1,subchange');

    // AI corrections: free tier gets the mock inside the controller;
    // throttled tighter since premium requests cost real money.
    Route::post('/ai/correct', [AiController::class, 'correct'])->middleware('throttle:30,1,correct');

    // The Tilanteet catalog is browsable by everyone (free users see the
    // cards + paywall); actually chatting goes through the premium /chat.
    Route::get('/scenarios', [ChatController::class, 'scenarios']);

    // Recording studio (is_recorder users): human audio over TTS.
    Route::get('/record/queue', [RecordController::class, 'queue']);
    Route::get('/record/submitted', [RecordController::class, 'submitted']);
    Route::post('/record/sentence/{id}', [RecordController::class, 'storeSentence']);
    Route::delete('/record/sentence/{id}', [RecordController::class, 'revertSentence']);
    Route::post('/record/word', [RecordController::class, 'storeWord']);
    Route::delete('/record/word', [RecordController::class, 'revertWord']);

    // Admin panel (promote via `php artisan user:promote <email>`).
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/stats', [AdminController::class, 'stats']);
        Route::get('/trends', [AdminController::class, 'trends']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::post('/users/{user}/premium', [AdminController::class, 'togglePremium']);
        Route::post('/users/{user}/recorder', [AdminController::class, 'toggleRecorder']);
        Route::post('/users/{user}/verify-email', [AdminController::class, 'verifyEmail']);

        // Review pending recordings: approve → goes live, reject → back to queue.
        Route::get('/recordings', [RecordController::class, 'pending']);
        Route::post('/recordings/approve', [RecordController::class, 'approve']);
        Route::post('/recordings/reject', [RecordController::class, 'reject']);
    });

    // Löyly+ features. Completing a Situation lives here too: it awards XP
    // and can only legitimately happen from inside the premium /chat.
    Route::middleware('premium')->group(function () {
        Route::post('/chat', [ChatController::class, 'chat'])->middleware('throttle:20,1,chat');
        Route::post('/scenarios/{id}/complete', [ChatController::class, 'completeScenario']);
        Route::post('/tts', [TtsController::class, 'speak'])->middleware('throttle:30,1,tts');
        Route::get('/insights/week', [InsightsController::class, 'week']);
    });
});
