<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\AuthController;
Route::get('/', function () {
    return view('chat');
});


Route::post('/send-message', [ChatController::class, 'sendMessage']);
Route::get('/chat-history', [ChatController::class, 'getChatHistory']);
Route::post('/authenticate', [AuthController::class, 'authenticate']);
Route::get('/check-auth', [AuthController::class, 'checkAuth']);
Route::post('/telegram-callback', [AuthController::class, 'handleTelegramCallback']);