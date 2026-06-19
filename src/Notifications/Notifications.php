<?php

namespace BitDreamIT\MikoPBX\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;

/**
 * MissedCallNotification
 *
 * Send via email, Slack, or database when a call is missed.
 *
 * Usage:
 *   $agent->notify(new MissedCallNotification($caller, $extension));
 */
class MissedCallNotification extends Notification
{
    public function __construct(
        private string $callerNumber,
        private string $extension,
        private string $callerName   = '',
        private string $queueName    = '',
    ) {}

    public function via(object $notifiable): array
    {
        return config('mikopbx.notifications.channels', ['mail', 'database']);
    }

    // ── Email ─────────────────────────────────────────────────────

    public function toMail(object $notifiable): MailMessage
    {
        $caller = $this->callerName ?: $this->callerNumber;
        return (new MailMessage)
            ->subject('📞 Missed Call from ' . $caller)
            ->greeting('Missed Call Alert')
            ->line("You missed a call from **{$caller}** on extension **{$this->extension}**.")
            ->when($this->queueName, fn($m) => $m->line("Queue: {$this->queueName}"))
            ->line('Time: ' . now()->format('D, d M Y h:i A'))
            ->action('View Call Logs', url('/mikopbx/calls/logs'))
            ->line('Please call back at your earliest convenience.');
    }

    // ── Slack ─────────────────────────────────────────────────────

    public function toSlack(object $notifiable): SlackMessage
    {
        $caller = $this->callerName ?: $this->callerNumber;
        return (new SlackMessage)
            ->warning()
            ->content("📞 *Missed Call Alert*")
            ->attachment(function ($a) use ($caller) {
                $a->title('Missed Call Details')
                  ->fields([
                      'Caller'    => $caller,
                      'Extension' => $this->extension,
                      'Time'      => now()->format('h:i A'),
                      'Queue'     => $this->queueName ?: 'Direct',
                  ]);
            });
    }

    // ── Database ──────────────────────────────────────────────────

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'          => 'missed_call',
            'caller_number' => $this->callerNumber,
            'caller_name'   => $this->callerName,
            'extension'     => $this->extension,
            'queue'         => $this->queueName,
            'time'          => now()->toISOString(),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}

/**
 * VoicemailNotification
 * Notify agent when they receive a new voicemail.
 */
class VoicemailNotification extends Notification
{
    public function __construct(
        private string $callerNumber,
        private string $extension,
        private int    $duration       = 0,
        private ?string $recordingFile = null,
    ) {}

    public function via(object $notifiable): array
    {
        return config('mikopbx.notifications.channels', ['mail', 'database']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('📩 New Voicemail from ' . $this->callerNumber)
            ->greeting('New Voicemail')
            ->line("You have a new voicemail from **{$this->callerNumber}** on extension **{$this->extension}**.")
            ->line('Duration: ' . gmdate('i:s', $this->duration))
            ->line('Received: ' . now()->format('D, d M Y h:i A'));

        if ($this->recordingFile) {
            $mail->action('Listen to Voicemail', url('/mikopbx/recordings/' . $this->recordingFile . '/download'));
        }

        return $mail;
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'           => 'voicemail',
            'caller_number'  => $this->callerNumber,
            'extension'      => $this->extension,
            'duration'       => $this->duration,
            'recording_file' => $this->recordingFile,
            'time'           => now()->toISOString(),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}

/**
 * CampaignCompletedNotification
 * Notify manager when a dialer campaign finishes.
 */
class CampaignCompletedNotification extends Notification
{
    public function __construct(
        private string $campaignName,
        private int    $total,
        private int    $answered,
        private int    $missed,
    ) {}

    public function via(object $notifiable): array
    {
        return config('mikopbx.notifications.channels', ['mail', 'database']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $rate = $this->total > 0 ? round(($this->answered / $this->total) * 100) : 0;
        return (new MailMessage)
            ->subject("✅ Campaign [{$this->campaignName}] Completed")
            ->greeting('Campaign Completed')
            ->line("Your campaign **{$this->campaignName}** has finished.")
            ->line("📊 **Results:**")
            ->line("Total numbers: {$this->total}")
            ->line("Answered: {$this->answered} ({$rate}%)")
            ->line("Missed: {$this->missed}")
            ->action('View Campaign Report', url('/mikopbx/campaigns'));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'          => 'campaign_completed',
            'campaign_name' => $this->campaignName,
            'total'         => $this->total,
            'answered'      => $this->answered,
            'missed'        => $this->missed,
            'answer_rate'   => $this->total > 0 ? round(($this->answered / $this->total) * 100) : 0,
            'time'          => now()->toISOString(),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}

/**
 * CallbackReminderNotification
 * Remind agent to call back a missed caller.
 */
class CallbackReminderNotification extends Notification
{
    public function __construct(
        private string $callerNumber,
        private string $extension    = '',
        private int    $attempts     = 0,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('⏰ Callback Reminder: ' . $this->callerNumber)
            ->greeting('Callback Reminder')
            ->line("Please call back **{$this->callerNumber}** who tried to reach extension **{$this->extension}**.")
            ->when($this->attempts > 1, fn($m) => $m->line("Auto-callback has failed {$this->attempts} time(s). Manual callback required."))
            ->action('Call Now', url('/mikopbx/calls/originate?from=' . $this->extension . '&to=' . $this->callerNumber));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'          => 'callback_reminder',
            'caller_number' => $this->callerNumber,
            'extension'     => $this->extension,
            'attempts'      => $this->attempts,
            'time'          => now()->toISOString(),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
