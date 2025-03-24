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
            Log::info('Telegram callback data received', [
                'data' => $telegramData,
                'method' => $request->method(),
                'url' => $request->fullUrl()
            ]);

            $verificationResult = $this->verifyTelegramData($telegramData);
            if (!$verificationResult['success']) {
                Log::warning('Telegram data verification failed', [
                    'data' => $telegramData,
                    'reason' => $verificationResult['reason'],
                    'bot_token' => config('services.telegram.bot_token')
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Telegram authentication data: ' . $verificationResult['reason']
                ], 401);
            }

            session([
                'telegram_authenticated' => true,
                'telegram_user_id' => $telegramData['id'],
                'telegram_username' => $telegramData['username'] ?? 'N/A',
                'telegram_first_name' => $telegramData['first_name'] ?? 'Unknown',
            ]);

            Log::info('User authenticated via Telegram', $telegramData);
            return redirect('/')->with('message', 'Authenticated successfully');

        } catch (\Exception $e) {
            Log::error('Telegram auth error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $telegramData
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
            return ['success' => false, 'reason' => 'Bot token not configured'];
        }

        Log::info('Verifying Telegram data', ['data' => $data]);

        if (!isset($data['id']) || !isset($data['hash']) || !isset($data['auth_date'])) {
            $missing = [];
            if (!isset($data['id'])) $missing[] = 'id';
            if (!isset($data['hash'])) $missing[] = 'hash';
            if (!isset($data['auth_date'])) $missing[] = 'auth_date';
            return ['success' => false, 'reason' => 'Missing required fields: ' . implode(', ', $missing)];
        }

        $checkHash = $data['hash'];
        $dataForHash = $data;
        unset($dataForHash['hash']);

        $dataCheckString = collect($dataForHash)
            ->sortKeys()
            ->map(fn($value, $key) => "$key=$value")
            ->implode("\n");

        Log::info('Computed data check string', ['string' => $dataCheckString]);

        $secretKey = hash('sha256', $botToken, true);
        $computedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        Log::info('Hash comparison', [
            'computed' => $computedHash,
            'received' => $checkHash
        ]);

        $isValid = hash_equals($computedHash, $checkHash);
        if (!$isValid) {
            return ['success' => false, 'reason' => 'Hash mismatch'];
        }

        $currentTime = time();
        $authTime = (int) $data['auth_date'];
        $isFresh = ($currentTime - $authTime) < 86400;
        Log::info('Time validation', [
            'current_time' => $currentTime,
            'auth_date' => $authTime,
            'time_diff' => $currentTime - $authTime,
            'isFresh' => $isFresh
        ]);

        if (!$isFresh) {
            return ['success' => false, 'reason' => 'Auth date expired'];
        }

        return ['success' => true, 'reason' => 'Valid'];
    }

    public function checkAuth()
    {
        return response()->json([
            'success' => true,
            'authenticated' => session('telegram_authenticated', false)
        ]);
    }
}