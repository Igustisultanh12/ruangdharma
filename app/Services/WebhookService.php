<?php
namespace App\Services;
use App\Models\Webhook;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    /** Kirim event ke semua webhook aktif milik user (atau semua jika user null). */
    public static function dispatch(string $event, array $payload, ?int $userId = null): void
    {
        $query = Webhook::where('is_active', true);
        if ($userId) $query->where('user_id', $userId);
        foreach ($query->get() as $webhook) {
            if (!$webhook->listensTo($event)) continue;
            static::send($webhook, $event, $payload);
        }
    }
    public static function send(Webhook $webhook, string $event, array $payload): WebhookLog
    {
        $body = ['event' => $event, 'payload' => $payload, 'sent_at' => now()->toIso8601String()];
        try {
            $res = Http::timeout(10)->post($webhook->url, $body);
            return WebhookLog::create(['webhook_id' => $webhook->id, 'event' => $event, 'payload' => $body, 'status_code' => $res->status(), 'response' => mb_substr($res->body(), 0, 2000), 'created_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('Webhook gagal: ' . $e->getMessage());
            return WebhookLog::create(['webhook_id' => $webhook->id, 'event' => $event, 'payload' => $body, 'status_code' => 0, 'response' => $e->getMessage(), 'created_at' => now()]);
        }
    }
    public static function test(Webhook $webhook): WebhookLog
    {
        return static::send($webhook, 'webhook.test', ['message' => 'Tes webhook Ruang Dharma Studio OK.']);
    }
}
