<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected $botToken;
    protected $chatId;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token');
        $this->chatId = config('services.telegram.chat_id');

        if (!$this->botToken || !$this->chatId) {
            Log::error('Telegram configuration incomplete', [
                'bot_token' => $this->botToken,
                'chat_id' => $this->chatId
            ]);
        }
    }

    public function sendMessage($message)
    {
        if (!$this->botToken || !$this->chatId) {
            Log::error('Cannot send message: Telegram configuration missing');
            return false;
        }

        try {
            $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";

            $response = Http::timeout(10)
                ->post($url, [
                    'chat_id' => $this->chatId,
                    'text' => $message,
                    'parse_mode' => 'HTML',
                ]);

            $data = $response->json();

            if ($response->successful() && isset($data['ok']) && $data['ok']) {
                Log::info('Telegram message sent successfully');
                return true;
            }

            Log::warning('Telegram API failed', [
                'status' => $response->status(),
                'response' => $data
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('TelegramService error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}