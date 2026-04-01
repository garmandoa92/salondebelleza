<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private ?string $apiKey;
    private string $baseUrl = 'https://waba.360dialog.io/v1';

    public function __construct()
    {
        $this->apiKey = tenant()?->settings['whatsapp_api_key'] ?? null;
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    public function validatePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Ecuador: 09XXXXXXXX -> +5939XXXXXXXX
        if (str_starts_with($phone, '09') && strlen($phone) === 10) {
            return '+593' . substr($phone, 1);
        }
        // Already has country code
        if (str_starts_with($phone, '593')) {
            return '+' . $phone;
        }
        // Just 9 digits starting with 9
        if (str_starts_with($phone, '9') && strlen($phone) === 9) {
            return '+593' . $phone;
        }

        return '+' . $phone;
    }

    public function sendTemplate(string $phone, string $templateName, array $components = []): bool
    {
        if (! $this->isConfigured()) {
            Log::info("WhatsApp not configured. Would send template '{$templateName}' to {$phone}");
            return false;
        }

        try {
            $response = Http::withHeaders([
                'D360-API-KEY' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/messages", [
                'to' => $this->validatePhone($phone),
                'type' => 'template',
                'template' => [
                    'namespace' => tenant()?->settings['whatsapp_namespace'] ?? 'default',
                    'name' => $templateName,
                    'language' => ['code' => 'es'],
                    'components' => $components,
                ],
            ]);

            if ($response->successful()) {
                Log::info("WhatsApp template '{$templateName}' sent to {$phone}");
                return true;
            }

            Log::warning("WhatsApp send failed", ['status' => $response->status(), 'body' => $response->body()]);
            return false;
        } catch (\Exception $e) {
            Log::error("WhatsApp error: {$e->getMessage()}");
            return false;
        }
    }

    public function sendText(string $phone, string $message): bool
    {
        if (! $this->isConfigured()) {
            Log::info("WhatsApp not configured. Would send to {$phone}: {$message}");
            return false;
        }

        try {
            $response = Http::withHeaders([
                'D360-API-KEY' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/messages", [
                'to' => $this->validatePhone($phone),
                'type' => 'text',
                'text' => ['body' => $message],
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error("WhatsApp error: {$e->getMessage()}");
            return false;
        }
    }

    public function getTemplates(): array
    {
        if (! $this->isConfigured()) return [];

        try {
            $response = Http::withHeaders([
                'D360-API-KEY' => $this->apiKey,
            ])->get("{$this->baseUrl}/configs/templates");

            return $response->successful() ? $response->json('waba_templates', []) : [];
        } catch (\Exception $e) {
            return [];
        }
    }
}
