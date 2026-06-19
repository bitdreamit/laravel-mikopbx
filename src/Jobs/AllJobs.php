<?php

namespace BitDreamIT\MikoPBX\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use BitDreamIT\MikoPBX\Facades\MikoPBX;
use BitDreamIT\MikoPBX\Models\{CallLog, Campaign, Extension};

// ── Sync Extensions Job ───────────────────────────────────────────

/**
 * SyncExtensionsJob
 * Sync all extension statuses from MikoPBX to local DB.
 * Schedule: every 5 minutes via Laravel Scheduler.
 *
 *   $schedule->job(SyncExtensionsJob::class)->everyFiveMinutes();
 */
class SyncExtensionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $statuses = MikoPBX::call()->getExtensionStatuses();
        $data     = $statuses['data'] ?? [];

        foreach ($data as $ext) {
            Extension::updateOrCreate(
                ['number' => $ext['number'] ?? ''],
                [
                    'online'       => in_array($ext['status'] ?? '', ['REGISTERED', 'OK']),
                    'status'       => $ext['status'] ?? 'UNREACHABLE',
                    'last_seen_at' => now(),
                ]
            );
        }

        Log::channel('mikopbx')->info('Extensions synced: ' . count($data));
    }
}

// ── Generate Campaign Report Job ──────────────────────────────────

/**
 * GenerateCampaignReportJob
 * Generate a full campaign report after completion.
 */
class GenerateCampaignReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private int $campaignId) {}

    public function handle(): void
    {
        $campaign = Campaign::with('numbers')->findOrFail($this->campaignId);

        $answerRate = $campaign->dialed_count > 0
            ? round(($campaign->answered_count / $campaign->dialed_count) * 100, 2)
            : 0;

        $report = [
            'campaign_id'    => $campaign->id,
            'campaign_name'  => $campaign->name,
            'type'           => $campaign->type,
            'status'         => $campaign->status,
            'total_numbers'  => $campaign->total_numbers,
            'dialed'         => $campaign->dialed_count,
            'answered'       => $campaign->answered_count,
            'missed'         => $campaign->missed_count,
            'failed'         => $campaign->failed_count,
            'answer_rate'    => $answerRate . '%',
            'started_at'     => $campaign->started_at?->toISOString(),
            'finished_at'    => $campaign->finished_at?->toISOString(),
            'duration_human' => $campaign->started_at && $campaign->finished_at
                ? $campaign->started_at->diffForHumans($campaign->finished_at, true)
                : null,
            'numbers_detail' => $campaign->numbers->groupBy('status')->map->count(),
            'generated_at'   => now()->toISOString(),
        ];

        \Illuminate\Support\Facades\Storage::put(
            "mikopbx/campaigns/report-{$this->campaignId}-" . now()->format('Ymd-His') . '.json',
            json_encode($report, JSON_PRETTY_PRINT)
        );

        Log::channel('mikopbx')->info("Campaign report generated for #{$this->campaignId}");
    }
}

// ── Clean Old Call Logs Job ───────────────────────────────────────

/**
 * CleanOldCallLogsJob
 * Remove call logs older than configured days.
 * Schedule: daily.
 *
 *   $schedule->job(CleanOldCallLogsJob::class)->daily();
 */
class CleanOldCallLogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $keepDays = 90) {}

    public function handle(): void
    {
        $deleted = CallLog::where('started_at', '<', now()->subDays($this->keepDays))->delete();
        Log::channel('mikopbx')->info("Cleaned {$deleted} old call log records (older than {$this->keepDays} days)");
    }
}

// ── Health Alert Job ──────────────────────────────────────────────

/**
 * MikoPBXHealthAlertJob
 * Run health check and alert if system is degraded/unhealthy.
 * Schedule: every 10 minutes.
 *
 *   $schedule->job(MikoPBXHealthAlertJob::class)->everyTenMinutes();
 */
class MikoPBXHealthAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $health = MikoPBX::health()->check();

        if ($health['overall'] !== 'healthy') {
            Log::channel('mikopbx')->error('MikoPBX health check failed', $health);

            $email = config('mikopbx.alert_email');
            if ($email) {
                \Illuminate\Support\Facades\Mail::raw(
                    "MikoPBX health check status: {$health['overall']}\n\n" . json_encode($health['checks'], JSON_PRETTY_PRINT),
                    fn($m) => $m->to($email)->subject("⚠️ MikoPBX Health Alert: {$health['overall']}")
                );
            }
        }
    }
}

// ── CDR Daily Sync Job ────────────────────────────────────────────

/**
 * CdrDailySyncJob
 * Automatically sync yesterday's CDR records to local DB.
 * Schedule: daily at midnight.
 *
 *   $schedule->job(CdrDailySyncJob::class)->dailyAt('00:05');
 */
class CdrDailySyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $yesterday = now()->subDay()->toDateString();

        try {
            $records = MikoPBX::call()->getRecordings($yesterday, $yesterday)['data'] ?? [];

            foreach ($records as $record) {
                \BitDreamIT\MikoPBX\Models\CdrSync::updateOrCreate(
                    ['uniqueid' => $record['uniqueid'] ?? uniqid()],
                    [
                        'src'           => $record['src']          ?? '',
                        'dst'           => $record['dst']          ?? '',
                        'duration'      => $record['duration']     ?? 0,
                        'billsec'       => $record['billsec']      ?? 0,
                        'disposition'   => $record['disposition']  ?? '',
                        'recordingfile' => $record['recordingfile']?? '',
                        'calldate'      => $record['calldate']     ?? now(),
                    ]
                );
            }

            Log::channel('mikopbx')->info("CDR synced for {$yesterday}: " . count($records) . " records");
        } catch (\Throwable $e) {
            Log::channel('mikopbx')->error("CDR sync failed for {$yesterday}: " . $e->getMessage());
        }
    }
}

// ── Blacklist Cleanup Job ─────────────────────────────────────────

/**
 * BlacklistCleanupJob
 * Remove expired blacklist entries.
 * Schedule: daily.
 */
class BlacklistCleanupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $count = \BitDreamIT\MikoPBX\Models\Blacklist::where('expires_at', '<', now())->delete();
        Log::channel('mikopbx')->info("Cleaned {$count} expired blacklist entries");
    }
}
