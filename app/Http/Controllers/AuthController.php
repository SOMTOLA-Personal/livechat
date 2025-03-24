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
                'telegram_first_name' => $telegramData['first_name'] ?? 'Unknown',
            ]);

            Log::info('User authenticated via Telegram', $telegramData);
            return redirect('/')->with('message', 'Authenticated successfully');

        } catch (\Exception $e) {
            Log::error('Telegram auth error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->query()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function verifyTelegramData($data)
    {
        $botToken = config('services.telegram.bot_token');
        if (empty($botToken)) {
            Log::error('Bot token not configured');
            return false;
        }

        // Log all incoming data for debugging
        Log::info('Verifying Telegram data', ['data' => $data]);

        // Check required fields
        if (!isset($data['id']) || !isset($data['hash']) || !isset($data['auth_date'])) {
            Log::warning('Missing required Telegram fields', [
                'id' => $data['id'] ?? 'missing',
                'hash' => $data['hash'] ?? 'missing',
                'auth_date' => $data['auth_date'] ?? 'missing'
            ]);
            return false;
        }

        // Prepare data for hash computation
        $checkHash = $data['hash'];
        $dataForHash = $data;
        unset($dataForHash['hash']); // Remove hash from computation

        // Ensure proper string conversion and sorting
        $dataCheckString = collect($dataForHash)
            ->sortKeys()
            ->map(fn($value, $key) => "$key=$value")
            ->implode("\n");

        Log::info('Computed data check string', ['string' => $dataCheckString]);

        // Compute HMAC-SHA256 hash
        $secretKey = hash('sha256', $botToken, true);
        $computedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        Log::info('Hash comparison', [
            'computed' => $computedHash,
            'received' => $checkHash
        ]);

        // Validate hash and freshness
        $isValid = hash_equals($computedHash, $checkHash);
        $currentTime = time();
        $authTime = (int) $data['auth_date'];
        $isFresh = ($currentTime - $authTime) < 86400; // 24-hour window

        Log::info('Validation results', [
            'isValid' => $isValid,
            'isFresh' => $isFresh,
            'current_time' => $currentTime,
            'auth_date' => $authTime,
            'time_diff' => $currentTime - $authTime
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