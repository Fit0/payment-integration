<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\WebhookController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/process', [PaymentController::class, 'processPayment'])->defaults('gateway', 'easy-money');
Route::post('/pay', [PaymentController::class, 'processPayment'])->defaults('gateway', 'super-walletz');

// Webhook routes
Route::prefix('webhooks')->group(function () {
    Route::post('/{gateway}', [WebhookController::class, 'handleWebhook'])
        ->where('gateway', 'easy-money|super-walletz');
});
