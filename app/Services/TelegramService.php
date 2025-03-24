<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected $botToken;
    protected $defaultChatId;
    protected $apiUrl;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token');
        $this->defaultChatId = config('services.telegram.chat_id');
        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}/";

        if (!$this->botToken) {
            Log::error('Telegram bot token missing in configuration');
        }
    }

    public function sendMessage($message, $chatId = null)
    {
        if (!$this->botToken) {
            Log::error('Cannot send message: Telegram bot token missing');
            return false;
        }

        $chatId = $chatId ?? $this->defaultChatId;
        if (!$chatId) {
            Log::error('Cannot send message: Chat ID not provided and no default set');
            return false;
        }

        try {
            $response = Http::timeout(10)
                ->post("{$this->apiUrl}sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'HTML',
                ]);

            $data = $response->json();

            if ($response->successful() && isset($data['ok']) && $data['ok']) {
                Log::info('Telegram message sent successfully', [
                    'chat_id' => $chatId,
                    'message' => $message
                ]);
                return true;
            }

            Log::warning('Telegram API send message failed', [
                'status' => $response->status(),
                'response' => $data,
                'chat_id' => $chatId
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('TelegramService sendMessage error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'chat_id' => $chatId
            ]);
            return false;
        }
    }

    public function setWebhook($webhookUrl)
    {
        if (!$this->botToken) {
            Log::error('Cannot set webhook: Telegram bot token missing');
            return false;
        }

        try {
            $response = Http::timeout(10)
                ->post("{$this->apiUrl}setWebhook", [
                    'url' => $webhookUrl,
                ]);

            $data = $response->json();

            if ($response->successful() && isset($data['ok']) && $data['ok']) {
                Log::info('Telegram webhook set successfully', [
                    'url' => $webhookUrl
                ]);
                return true;
            }

            Log::warning('Telegram webhook setup failed', [
                'status' => $response->status(),
                'response' => $data
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('TelegramService setWebhook error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'webhook_url' => $webhookUrl
            ]);
            return false;
        }
    }

    public function getWebhookInfo()
    {
        if (!$this->botToken) {
            Log::error('Cannot get webhook info: Telegram bot token missing');
            return false;
        }

        try {
            $response = Http::timeout(10)
                ->get("{$this->apiUrl}getWebhookInfo");

            $data = $response->json();

            if ($response->successful() && isset($data['ok']) && $data['ok']) {
                Log::info('Telegram webhook info retrieved successfully');
                return $data['result'];
            }

            Log::warning('Telegram get webhook info failed', [
                'status' => $response->status(),
                'response' => $data
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('TelegramService getWebhookInfo error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function deleteWebhook()
    {
        if (!$this->botToken) {
            Log::error('Cannot delete webhook: Telegram bot token missing');
            return false;
        }

        try {
            $response = Http::timeout(10)
                ->post("{$this->apiUrl}deleteWebhook");

            $data = $response->json();

            if ($response->successful() && isset($data['ok']) && $data['ok']) {
                Log::info('Telegram webhook deleted successfully');
                return true;
            }

            Log::warning('Telegram delete webhook failed', [
                'status' => $response->status(),
                'response' => $data
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('TelegramService deleteWebhook error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}