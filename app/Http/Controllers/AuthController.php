<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function handleTelegramCallback(Request $request)
    {
        try {
            $telegramData = $request->query();
            Log::info('Telegram callback data received', $telegramData);

            if (!$this->verifyTelegramData($telegramData)) {
                Log::warning('Telegram data verification failed', [
                    'data' => $telegramData,
                    'bot_token' => config('services.telegram.bot_token')
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Telegram authentication data'
                ], 401);
            }

            session([
                'telegram_authenticated' => true,
                'telegram_user_id' => $telegramData['id'],
                'telegram_username' => $telegramData['username'] ?? null,
                'telegram_first_name' => $telegramData['first_name'],
            ]);

            Log::info('User authenticated via Telegram', $telegramData);
            return redirect('/')->with('message', 'Authenticated successfully');

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
        Log::info('Verifying Telegram data', ['bot_token' => $botToken, 'data' => $data]);

        // Check required fields
        if (!isset($data['id'], $data['hash'], $data['auth_date'])) {
            Log::warning('Missing required fields', $data);
            return false;
        }

        $checkHash = $data['hash'];
        unset($data['hash']);
        $dataCheckString = collect($data)
            ->sortKeys()
            ->map(fn($value, $key) => "$key=$value")
            ->implode("\n");

        Log::info('Data check string', ['string' => $dataCheckString]);

        $secretKey = hash('sha256', $botToken, true);
        $computedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        Log::info('Hash comparison', [
            'computed' => $computedHash,
            'received' => $checkHash
        ]);

        $isValid = hash_equals($computedHash, $checkHash);
        $isFresh = (time() - $data['auth_date']) < 86400;

        Log::info('Validation result', [
            'isValid' => $isValid,
            'isFresh' => $isFresh,
            'time_diff' => time() - $data['auth_date']
        ]);

        return $isValid && $isFresh;
    }

    public function checkAuth()
    {
        return response()->json([
            'success' => true,
            'authenticated' => session('telegram_authenticated', false)
        ]);
    }
}