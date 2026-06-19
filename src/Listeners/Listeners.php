<?php

namespace BitDreamIT\MikoPBX\Listeners;

use BitDreamIT\MikoPBX\Events\IncomingCallEvent;
use BitDreamIT\MikoPBX\Events\CallEndedEvent;
use BitDreamIT\MikoPBX\Events\CallMissedEvent;
use BitDreamIT\MikoPBX\Events\CampaignCompletedEvent;
use BitDreamIT\MikoPBX\Events\NewVoicemailEvent;
use BitDreamIT\MikoPBX\Models\{CallLog, Extension, CallbackRequest};
use BitDreamIT\MikoPBX\Services\SmsNotificationService;
use BitDreamIT\MikoPBX\Notifications\{MissedCallNotification, VoicemailNotification, CampaignCompletedNotification};
use BitDreamIT\MikoPBX\Jobs\ProcessCallbackJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

// ── Handle Incoming Call ──────────────────────────────────────────

/**
 * HandleIncomingCall
 *
 * Fires on every inbound call:
 * - Looks up caller in your app's model
 * - Broadcasts to agent browser via Laravel Echo
 * - Updates CRM if customer found
 *
 * Register in EventServiceProvider:
 *   IncomingCallEvent::class => [HandleIncomingCall::class]
 */
class HandleIncomingCall implements ShouldQueue
{
    public function handle(IncomingCallEvent $event): void
    {
        Log::channel('mikopbx')->info("Incoming call: {$event->callerNumber} -> ext {$event->extension}");

        // Update call log with any CRM data you have
        CallLog::where('channel', $event->channel)->update([
            'caller_name' => $event->callerName,
        ]);
    }
}

// ── Handle Call Ended ─────────────────────────────────────────────

/**
 * HandleCallEnded
 *
 * Fires when any call finishes.
 * - Updates call log with final state
 * - Schedules missed call callback
 * - Sends SMS alert for missed calls
 */
class HandleCallEnded implements ShouldQueue
{
    public function __construct(private SmsNotificationService $sms) {}

    public function handle(CallEndedEvent $event): void
    {
        Log::channel('mikopbx')->info("Call ended: {$event->channel} | {$event->duration}s | {$event->cause}");

        if ($event->isMissed()) {
            // Fire missed event
            $log = CallLog::where('channel', $event->channel)->latest()->first();
            if ($log) {
                event(new CallMissedEvent($log->caller ?? '', $event->extension, $event->channel));
            }
        }
    }
}

// ── Handle Missed Call ────────────────────────────────────────────

/**
 * HandleMissedCall
 *
 * Fires specifically for missed calls.
 * - Schedules auto-callback job
 * - Sends SMS to agent
 * - Sends Laravel notification
 */
class HandleMissedCall implements ShouldQueue
{
    public function __construct(private SmsNotificationService $sms) {}

    public function handle(CallMissedEvent $event): void
    {
        Log::channel('mikopbx')->info("Missed call: {$event->callerNumber} on ext {$event->extension}");

        // Auto-schedule callback
        $callback = CallbackRequest::create([
            'caller_number' => $event->callerNumber,
            'extension'     => $event->extension,
            'reason'        => 'missed_call',
            'status'        => 'pending',
            'scheduled_at'  => now()->addMinutes(config('mikopbx.retry_delay_minutes', 5)),
            'max_attempts'  => config('mikopbx.max_retry_attempts', 3),
        ]);

        ProcessCallbackJob::dispatch($callback)->delay(now()->addMinutes(5));

        // Notify agent via Laravel notification (email/DB/Slack)
        if (config('mikopbx.notifications.missed_call')) {
            $agent = Extension::where('number', $event->extension)->first();
            if ($agent) {
                // Assumes Extension has $notifiable or use Notification::route()
                Notification::route('mail', $agent->email ?? null)
                    ->notify(new MissedCallNotification(
                        $event->callerNumber,
                        $event->extension,
                        $event->callerName ?? '',
                    ));
            }
        }

        // SMS alert
        $agentMobile = Extension::where('number', $event->extension)->value('mobile');
        if ($agentMobile) {
            $this->sms->missedCallAlert($agentMobile, $event->callerNumber, $event->extension);
        }
    }
}

// ── Handle Campaign Completed ─────────────────────────────────────

/**
 * HandleCampaignCompleted
 *
 * Fires when a dialer campaign finishes.
 * - Updates local campaign record
 * - Notifies manager
 * - Generates report
 */
class HandleCampaignCompleted implements ShouldQueue
{
    public function __construct(private SmsNotificationService $sms) {}

    public function handle(CampaignCompletedEvent $event): void
    {
        Log::channel('mikopbx')->info("Campaign completed: {$event->campaignName} | {$event->answered}/{$event->total} answered");

        \BitDreamIT\MikoPBX\Models\Campaign::find($event->campaignId)?->update([
            'status'         => 'finished',
            'finished_at'    => now(),
            'answered_count' => $event->answered,
            'missed_count'   => $event->missed,
        ]);

        // Notify manager
        if (config('mikopbx.notifications.campaign_complete')) {
            $managerEmail = config('mikopbx.campaign_manager_email');
            if ($managerEmail) {
                Notification::route('mail', $managerEmail)
                    ->notify(new CampaignCompletedNotification(
                        $event->campaignName,
                        $event->total,
                        $event->answered,
                        $event->missed,
                    ));
            }
        }

        // Generate report job
        \BitDreamIT\MikoPBX\Jobs\GenerateCampaignReportJob::dispatch($event->campaignId);
    }
}

// ── Handle New Voicemail ──────────────────────────────────────────

/**
 * HandleNewVoicemail
 *
 * Fires when a new voicemail is left.
 * - Notifies the extension owner
 * - Sends SMS if configured
 */
class HandleNewVoicemail implements ShouldQueue
{
    public function __construct(private SmsNotificationService $sms) {}

    public function handle(NewVoicemailEvent $event): void
    {
        Log::channel('mikopbx')->info("New voicemail: {$event->callerNumber} -> mailbox {$event->mailbox}");

        $extension = explode('@', $event->mailbox)[0];
        $agent     = Extension::where('number', $extension)->first();

        if ($agent && config('mikopbx.notifications.voicemail')) {
            Notification::route('mail', $agent->email ?? null)
                ->notify(new VoicemailNotification(
                    $event->callerNumber,
                    $extension,
                    $event->duration,
                    $event->recordingFile,
                ));
        }

        if ($agent?->mobile) {
            $this->sms->voicemailAlert($agent->mobile, $event->callerNumber);
        }
    }
}
