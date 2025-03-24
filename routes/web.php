<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\AuthController;

Route::get('/', function () { return view('chat'); }); // Assuming chat.blade.php
Route::get('/telegram-callback', [AuthController::class, 'handleTelegramCallback']);
Route::get('/check-auth', [AuthController::class, 'checkAuth']);
Route::post('/send-message', [ChatController::class, 'sendMessage']);
Route::get('/chat-history', [ChatController::class, 'getChatHistory']);