<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;

class SetupTelegramWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:setup-webhook 
                            {url? : The webhook URL to set (optional)} 
                            {--delete : Delete the existing webhook instead of setting a new one}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set up or delete Telegram webhook for the application';

    /**
     * The Telegram service instance.
     *
     * @var TelegramService
     */
    protected $telegramService;

    /**
     * Create a new command instance.
     *
     * @param TelegramService $telegramService
     * @return void
     */
    public function __construct(TelegramService $telegramService)
    {
        parent::__construct();
        $this->telegramService = $telegramService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            if ($this->option('delete')) {
                return $this->deleteWebhook();
            }

            return $this->setupWebhook();

        } catch (\Exception $e) {
            $this->error('An error occurred: ' . $e->getMessage());
            Log::error('Telegram webhook command error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Set up the webhook with the provided or configured URL.
     *
     * @return int
     */
    protected function setupWebhook()
    {
        // Get URL from argument or config
        $webhookUrl = $this->argument('url') ?? config('services.telegram.webhook_url');

        if (!$webhookUrl) {
            $this->error('Webhook URL not provided and not configured in services.telegram.webhook_url');
            return 1;
        }

        $this->info("Setting Telegram webhook to: {$webhookUrl}");

        // Check current webhook status
        $currentInfo = $this->telegramService->getWebhookInfo();
        if ($currentInfo && isset($currentInfo['url']) && $currentInfo['url'] === $webhookUrl) {
            $this->info('Webhook is already set to this URL');
            return 0;
        }

        // Set the webhook
        if ($this->telegramService->setWebhook($webhookUrl)) {
            $this->info('Webhook set successfully');
            
            // Verify the setup
            $updatedInfo = $this->telegramService->getWebhookInfo();
            if ($updatedInfo) {
                $this->line('Webhook Info:');
                $this->line('URL: ' . ($updatedInfo['url'] ?? 'Not set'));
                $this->line('Pending updates: ' . ($updatedInfo['pending_update_count'] ?? 0));
            }
            return 0;
        }

        $this->error('Failed to set webhook');
        return 1;
    }

    /**
     * Delete the existing webhook.
     *
     * @return int
     */
    protected function deleteWebhook()
    {
        $this->info('Attempting to delete Telegram webhook');

        if ($this->telegramService->deleteWebhook()) {
            $this->info('Webhook deleted successfully');
            
            $info = $this->telegramService->getWebhookInfo();
            if ($info && empty($info['url'])) {
                $this->line('Confirmed: No webhook URL is set');
            }
            return 0;
        }

        $this->error('Failed to delete webhook');
        return 1;
    }
}