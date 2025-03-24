<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\TelegramService;
use App\Models\Message;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    protected $telegram;

    public function __construct(TelegramService $telegram)
    {
        $this->telegram = $telegram;
    }

    public function sendMessage(Request $request)
    {
        try {
            // Check if user is authenticated (we'll add this later)
            if (!session('telegram_authenticated')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please authenticate with Telegram first'
                ], 403);
            }

            $validated = $request->validate([
                'message' => 'required|string|max:4096',
            ]);

            $clientMessage = $validated['message'];

            // Store client message
            Message::create([
                'sender' => 'client',
                'content' => $clientMessage
            ]);

            // Send to Telegram and get success status
            $telegramSuccess = $this->telegram->sendMessage($clientMessage);

            if (!$telegramSuccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send message to Telegram'
                ], 500);
            }

            // Server response (e.g., "Hello back!")
            $serverResponse = $clientMessage === 'hi' ? 'Hello back!' : 'Message received';
            Message::create([
                'sender' => 'server',
                'content' => $serverResponse
            ]);

            return response()->json([
                'success' => true,
                'message' => $serverResponse,
                'chat' => [
                    ['sender' => 'client', 'content' => $clientMessage],
                    ['sender' => 'server', 'content' => $serverResponse]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('ChatController error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    public function getChatHistory()
    {
        if (!session('telegram_authenticated')) {
            return response()->json([
                'success' => false,
                'message' => 'Please authenticate with Telegram first'
            ], 403);
        }

        $messages = Message::orderBy('created_at', 'asc')->get(['sender', 'content']);
        return response()->json([
            'success' => true,
            'chat' => $messages
        ]);
    }
}