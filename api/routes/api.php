<?php

use App\Http\Controllers\PaymentIntentController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/payment-intents', [PaymentIntentController::class, 'store']);
    Route::get('/payment-intents/{intent}', [PaymentIntentController::class, 'show']);
});

Route::post('/webhooks/payments', PaymentWebhookController::class)
    ->middleware(VerifyWebhookSignature::class);
