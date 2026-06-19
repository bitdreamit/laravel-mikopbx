<?php

namespace BitDreamIT\MikoPBX\Traits;

use BitDreamIT\MikoPBX\Models\CallLog;
use BitDreamIT\MikoPBX\Facades\MikoPBX;

/**
 * HasCallLogs — Add to any Eloquent model (Customer, Contact, Lead)
 * to get call history automatically linked by phone number.
 *
 * Usage:
 *   class Customer extends Model {
 *       use HasCallLogs;
 *       protected string $phoneColumn = 'mobile'; // optional, default 'phone'
 *   }
 *
 *   $customer->callLogs()             // all calls
 *   $customer->missedCalls()          // missed only
 *   $customer->lastCall()             // most recent
 *   $customer->totalCallDuration()    // total seconds
 *   $customer->callNow('101')         // originate call via ext 101
 */
trait HasCallLogs
{
    public function callLogs(): \Illuminate\Database\Eloquent\Relations\HasMany|\Illuminate\Database\Eloquent\Builder
    {
        $phone = $this->{$this->getPhoneColumn()};
        return CallLog::where('caller', $phone)->orWhere('destination', $phone)->latest('started_at');
    }

    public function missedCalls(): \Illuminate\Database\Eloquent\Builder
    {
        $phone = $this->{$this->getPhoneColumn()};
        return CallLog::where('caller', $phone)->where('status', 'missed')->latest('started_at');
    }

    public function answeredCalls(): \Illuminate\Database\Eloquent\Builder
    {
        $phone = $this->{$this->getPhoneColumn()};
        return CallLog::where('caller', $phone)->where('status', 'answered')->latest('started_at');
    }

    public function lastCall(): ?CallLog
    {
        return $this->callLogs()->first();
    }

    public function totalCallDuration(): int
    {
        $phone = $this->{$this->getPhoneColumn()};
        return CallLog::where('caller', $phone)->sum('duration');
    }

    public function callCount(): int
    {
        $phone = $this->{$this->getPhoneColumn()};
        return CallLog::where('caller', $phone)->count();
    }

    public function callNow(string $fromExtension): array
    {
        return MikoPBX::call()->originate($fromExtension, $this->{$this->getPhoneColumn()});
    }

    public function hasMissedCalls(): bool
    {
        return $this->missedCalls()->exists();
    }

    private function getPhoneColumn(): string
    {
        return property_exists($this, 'phoneColumn') ? $this->phoneColumn : 'phone';
    }
}

/**
 * HasMikoPBXEvents — Fire standard MikoPBX events from your model
 * when call-related state changes occur.
 */
trait HasMikoPBXEvents
{
    public function fireCallEvent(string $event, array $data = []): void
    {
        $class = 'BitDreamIT\\MikoPBX\\Events\\' . ucfirst(str_replace('.', '', $event)) . 'Event';
        if (class_exists($class)) {
            event(new $class(...$data));
        }
    }
}

/**
 * FormatsCallDuration — Format call durations nicely
 */
trait FormatsCallDuration
{
    public function formatDuration(int $seconds): string
    {
        if ($seconds < 60) return "{$seconds}s";
        $m = intdiv($seconds, 60);
        $s = $seconds % 60;
        if ($m < 60) return "{$m}m {$s}s";
        $h = intdiv($m, 60);
        $m = $m % 60;
        return "{$h}h {$m}m {$s}s";
    }

    public function formatDurationHuman(int $seconds): string
    {
        if ($seconds < 60) return "less than a minute";
        $m = intdiv($seconds, 60);
        if ($m === 1) return "1 minute";
        if ($m < 60) return "{$m} minutes";
        $h = intdiv($m, 60);
        $rem = $m % 60;
        $str = $h === 1 ? "1 hour" : "{$h} hours";
        if ($rem > 0) $str .= " and {$rem} " . ($rem === 1 ? "minute" : "minutes");
        return $str;
    }
}

/**
 * ValidatesPhoneNumber — Validate and normalize BD phone numbers
 */
trait ValidatesPhoneNumber
{
    public function normalizePhone(string $phone): string
    {
        // Strip all non-numeric except leading +
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);
        // Bangladesh: +880 prefix
        if (str_starts_with($cleaned, '880')) return '+' . $cleaned;
        if (str_starts_with($cleaned, '0'))  return '+880' . substr($cleaned, 1);
        return $cleaned;
    }

    public function isValidBDPhone(string $phone): bool
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        // 11 digits starting with 01 or 880 + 10 digits
        return preg_match('/^(880)?01[3-9]\d{8}$/', $cleaned) === 1;
    }

    public function formatPhoneDisplay(string $phone): string
    {
        $n = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($n) === 11 && str_starts_with($n, '01')) {
            return substr($n, 0, 3) . '-' . substr($n, 3, 4) . '-' . substr($n, 7);
        }
        return $phone;
    }
}
