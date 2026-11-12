<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ManagementController;
use App\Http\Controllers\ReceiveController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect(auth()->check() ? '/dashboard' : '/login'));
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1');
});
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth');
Route::get('/receive/{token}', [ReceiveController::class, 'show'])->middleware('throttle:60,1');
Route::post('/receive/{token}/link', [ReceiveController::class, 'acquirePublic'])->middleware('throttle:15,1');
Route::get('/leases/{lease}', [ReceiveController::class, 'poll'])->middleware('throttle:lease-poll');
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [ManagementController::class, 'dashboard'])->name('dashboard');
    Route::get('/manage/{resource}', [ManagementController::class, 'index']);
    Route::get('/manage/{resource}/create', [ManagementController::class, 'create']);
    Route::post('/manage/{resource}', [ManagementController::class, 'store'])->middleware('throttle:30,1');
    Route::get('/manage/{resource}/{id}/edit', [ManagementController::class, 'edit'])->whereNumber('id');
    Route::put('/manage/{resource}/{id}', [ManagementController::class, 'update'])->whereNumber('id');
    Route::get('/mailboxes/{mailbox}', [ManagementController::class, 'mailbox']);
    Route::get('/inbox', [ManagementController::class, 'inbox']);
    Route::get('/messages/{message}', [ManagementController::class, 'message']);
    Route::post('/subscriptions/{subscription}/grants', [ManagementController::class, 'grant']);
    Route::delete('/grants/{grant}', [ManagementController::class, 'revoke']);
    Route::get('/subscriptions/{subscription}/receive', [ReceiveController::class, 'showInternal']);
    Route::post('/subscriptions/{subscription}/link', [ReceiveController::class, 'acquireInternal'])->middleware('throttle:15,1');
});
