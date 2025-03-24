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
            if (!session('telegram_authenticated')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please authenticate with Telegram first'
                ], 403);
            }

            Log::info('Processing sendMessage', ['request' => $request->all()]);

            $validated = $request->validate([
                'message' => 'required|string|max:4096',
            ]);

            $clientMessage = $validated['message'];
            Log::info('Validated client message', ['message' => $clientMessage]);

            // Store client message
            $clientMsg = Message::create([
                'sender' => 'client',
                'content' => $clientMessage
            ]);
            Log::info('Client message stored', ['id' => $clientMsg->id]);

            // Send to Telegram
            $telegramSuccess = $this->telegram->sendMessage($clientMessage);
            Log::info('Telegram send result', ['success' => $telegramSuccess]);

            if (!$telegramSuccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send message to Telegram'
                ], 500);
            }

            $serverResponse = $clientMessage === 'hi' ? 'How can i help you?' : 'Message received';
            $serverMsg = Message::create([
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

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation error in sendMessage', [
                'errors' => $e->errors(),
                'request' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', $e->errors()['message'] ?? ['Invalid input'])
            ], 422);
        } catch (\Exception $e) {
            Log::error('ChatController error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function handleWebhook(Request $request)
    {
        try {
            // Get the Telegram update
            $update = $request->all();
            Log::info('Received Telegram webhook', ['update' => $update]);

            // Check if it's a message
            if (isset($update['message']) && isset($update['message']['text'])) {
                $telegramMessage = $update['message']['text'];
                $chatId = $update['message']['chat']['id'];

                // Store the received message
                $receivedMsg = Message::create([
                    'sender' => 'telegram',
                    'content' => $telegramMessage
                ]);
                Log::info('Telegram message stored', ['id' => $receivedMsg->id]);

                // Optional: Send an automatic response back to Telegram
                $response = "Received your message: " . $telegramMessage;
                $this->telegram->sendMessage($response, $chatId);

                // You might want to broadcast this to your frontend using Laravel Echo or similar
                // broadcast(new MessageReceived($receivedMsg))->toOthers();

                return response()->json(['success' => true]);
            }

            return response()->json(['success' => true, 'message' => 'No message to process']);

        } catch (\Exception $e) {
            Log::error('Webhook error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed'
            ], 500);
        }
    }

    public function getChatHistory()
    {
        try {
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
        } catch (\Exception $e) {
            Log::error('Chat history error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load chat history'
            ], 500);
        }
    }
}