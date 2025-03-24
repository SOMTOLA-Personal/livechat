<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function handleTelegramCallback(Request $request)
    {
        try {
            $telegramData = $request->all();

            // Validate Telegram data
            if (!$this->verifyTelegramData($telegramData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Telegram authentication data'
                ], 401);
            }

            // Store user authentication in session
            session([
                'telegram_authenticated' => true,
                'telegram_user_id' => $telegramData['id'],
                'telegram_username' => $telegramData['username'] ?? null,
                'telegram_first_name' => $telegramData['first_name'],
            ]);

            Log::info('User authenticated via Telegram', $telegramData);

            return response()->json([
                'success' => true,
                'message' => 'Successfully authenticated with Telegram'
            ]);

        } catch (\Exception $e) {
            Log::error('Telegram auth error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed'
            ], 500);
        }
    }

    private function verifyTelegramData($data)
    {
        $botToken = config('services.telegram.bot_token');
        if (!isset($data['id'], $data['hash'], $data['auth_date'])) {
            return false;
        }

        $checkHash = $data['hash'];
        unset($data['hash']);
        $dataCheckString = collect($data)
            ->sortKeys()
            ->map(fn($value, $key) => "$key=$value")
            ->implode("\n");

        $secretKey = hash('sha256', $botToken, true);
        $computedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        return hash_equals($computedHash, $checkHash) && (time() - $data['auth_date']) < 86400;
    }

    public function checkAuth()
    {
        return response()->json([
            'success' => true,
            'authenticated' => session('telegram_authenticated', false)
        ]);
    }
}