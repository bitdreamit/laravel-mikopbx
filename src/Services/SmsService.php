<?php
namespace BitDreamIT\MikoPBX\Services;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function send(string $to, string $message): bool
    {
        if (! config('mikopbx.features.sms_alerts')) return false;

        try {
            match(config('mikopbx.sms.driver')) {
                'ssl_wireless' => $this->sendViaSslWireless($to, $message),
                'twilio'       => $this->sendViaTwilio($to, $message),
                default        => Log::warning("MikoPBX SMS: unknown driver"),
            };
            return true;
        } catch (\Throwable $e) {
            Log::error("MikoPBX SMS send failed: {$e->getMessage()}");
            return false;
        }
    }

    private function sendViaSslWireless(string $to, string $message): void
    {
        Http::get('http://sms.sslwireless.com/pushapi/dynamic/server.php', [
            'api_token' => config('mikopbx.sms.api_key'),
            'sid'       => config('mikopbx.sms.from'),
            'msisdn'    => $to,
            'sms'       => $message,
            'csmsid'    => uniqid(),
        ])->throw();
    }

    private function sendViaTwilio(string $to, string $message): void
    {
        // Twilio SDK integration point
        Log::info("Twilio SMS to {$to}: {$message}");
    }
}
