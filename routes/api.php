<?php

use App\Http\Controllers\InboundEmailController;
use Illuminate\Support\Facades\Route;

Route::post('/inbound-email', InboundEmailController::class)->middleware('throttle:300,1');
