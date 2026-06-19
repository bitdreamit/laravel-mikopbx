<?php

namespace BitDreamIT\MikoPBX\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SMS Notification Service
 *
 * Send SMS alerts for missed calls, voicemails, and campaign results.
 * Supports: Twilio, Vonage, SSL Wireless BD, custom HTTP gateway.
 */
class SmsNotificationService
{
    private string $driver;
    private array  $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->driver = $config['sms']['driver'] ?? 'custom';
    }

    // ─────────────────────────────────────────
    // SEND
    // ─────────────────────────────────────────

    public function send(string $to, string $message): bool
    {
        return match($this->driver) {
            'twilio'     => $this->sendViaTwilio($to, $message),
            'vonage'     => $this->sendViaVonage($to, $message),
            'ssl_bd'     => $this->sendViaSSLBD($to, $message),
            'custom'     => $this->sendViaCustomGateway($to, $message),
            default      => throw new \InvalidArgumentException("Unknown SMS driver: {$this->driver}"),
        };
    }

    // ─────────────────────────────────────────
    // PRESET MESSAGES
    // ─────────────────────────────────────────

    public function missedCallAlert(string $agentPhone, string $callerNumber, string $extension): bool
    {
        $message = "📞 Missed call from {$callerNumber} on ext {$extension} at " . now()->format('h:i A');
        return $this->send($agentPhone, $message);
    }

    public function voicemailAlert(string $agentPhone, string $callerNumber): bool
    {
        $message = "📩 New voicemail from {$callerNumber}. Login to check your messages.";
        return $this->send($agentPhone, $message);
    }

    public function campaignCompleted(string $managerPhone, string $campaignName, int $answered, int $total): bool
    {
        $rate    = $total > 0 ? round(($answered / $total) * 100) : 0;
        $message = "✅ Campaign [{$campaignName}] complete. {$answered}/{$total} answered ({$rate}%).";
        return $this->send($managerPhone, $message);
    }

    public function callbackReminder(string $agentPhone, string $callerNumber): bool
    {
        $message = "⏰ Callback reminder: Call back {$callerNumber} now.";
        return $this->send($agentPhone, $message);
    }

    // ─────────────────────────────────────────
    // DRIVERS
    // ─────────────────────────────────────────

    private function sendViaTwilio(string $to, string $message): bool
    {
        $sid   = $this->config['sms']['twilio_sid'];
        $token = $this->config['sms']['twilio_token'];
        $from  = $this->config['sms']['twilio_from'];

        $r = Http::withBasicAuth($sid, $token)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'To'   => $to,
                'From' => $from,
                'Body' => $message,
            ]);

        if ($r->failed()) { Log::error('Twilio SMS failed', $r->json()); return false; }
        return true;
    }

    private function sendViaVonage(string $to, string $message): bool
    {
        $r = Http::post('https://rest.nexmo.com/sms/json', [
            'api_key'    => $this->config['sms']['vonage_key'],
            'api_secret' => $this->config['sms']['vonage_secret'],
            'to'         => ltrim($to, '+'),
            'from'       => $this->config['sms']['vonage_from'],
            'text'       => $message,
        ]);

        if ($r->json('messages.0.status') !== '0') { Log::error('Vonage SMS failed', $r->json()); return false; }
        return true;
    }

    private function sendViaSSLBD(string $to, string $message): bool
    {
        // SSL Wireless Bangladesh SMS Gateway
        $r = Http::get($this->config['sms']['ssl_bd_url'], [
            'api_key'  => $this->config['sms']['ssl_bd_api_key'],
            'senderid' => $this->config['sms']['ssl_bd_sender'],
            'number'   => $to,
            'message'  => $message,
        ]);

        if ($r->failed()) { Log::error('SSL BD SMS failed', ['response' => $r->body()]); return false; }
        return true;
    }

    private function sendViaCustomGateway(string $to, string $message): bool
    {
        $url    = $this->config['sms']['custom_url'] ?? '';
        $params = array_merge($this->config['sms']['custom_params'] ?? [], ['to' => $to, 'message' => $message]);

        if (!$url) { Log::warning('MikoPBX: No SMS gateway URL configured'); return false; }

        $r = Http::get($url, $params);
        if ($r->failed()) { Log::error('Custom SMS gateway failed', ['response' => $r->body()]); return false; }
        return true;
    }
}
