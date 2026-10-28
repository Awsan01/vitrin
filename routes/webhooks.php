<?php

use App\Http\Controllers\PaddleWebhookController;
use App\Http\Middleware\VerifyPaddleSignature;
use Illuminate\Support\Facades\Route;

/*
| Webhooks from outside services. These routes skip the "web" middleware:
| no session, cookies or CSRF token, since the caller is a server that
| proves itself with a signature instead.
*/

Route::post('webhooks/paddle', PaddleWebhookController::class)
    ->middleware(VerifyPaddleSignature::class)
    ->name('webhooks.paddle');
