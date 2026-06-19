<?php

namespace BitDreamIT\MikoPBX\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use BitDreamIT\MikoPBX\Facades\MikoPBX;
use BitDreamIT\MikoPBX\Models\Callback;

// ── Scheduled Callback Job ────────────────────────────────────────

class ScheduleCallbackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(
        private string $callerNumber,
        private string $extension    = '',
        private int    $maxAttempts  = 3
    ) {}

    public function handle(): void
    {
        Log::info("Callback attempt for {$this->callerNumber}");

        // Find or create callback record
        $callback = \BitDreamIT\MikoPBX\Models\CallLog::create([
            'caller'    => $this->callerNumber,
            'extension' => $this->extension,
            'direction' => 'outbound',
            'status'    => 'ringing',
            'started_at'=> now(),
        ]);

        // Make the call
        $result = MikoPBX::call()->originate(
            $this->extension ?: '100',
            $this->callerNumber
        );

        Log::info("Callback result", $result);
    }
}

// ── Sync Extensions Job ───────────────────────────────────────────

class SyncExtensionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $statuses = MikoPBX::call()->getExtensionStatuses();
        $data     = $statuses['data'] ?? [];

        foreach ($data as $ext) {
            \BitDreamIT\MikoPBX\Models\Extension::updateOrCreate(
                ['number' => $ext['number'] ?? ''],
                [
                    'online'       => in_array($ext['status'] ?? '', ['REGISTERED', 'OK']),
                    'status'       => $ext['status'] ?? 'UNREACHABLE',
                    'last_seen_at' => now(),
                ]
            );
        }

        Log::info('MikoPBX extensions synced: ' . count($data));
    }
}

// ── Campaign Report Job ───────────────────────────────────────────

class GenerateCampaignReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $campaignId) {}

    public function handle(): void
    {
        $campaign = \BitDreamIT\MikoPBX\Models\Campaign::with('numbers')->findOrFail($this->campaignId);

        $report = [
            'campaign'       => $campaign->name,
            'status'         => $campaign->status,
            'total'          => $campaign->total_numbers,
            'dialed'         => $campaign->dialed_count,
            'answered'       => $campaign->answered_count,
            'missed'         => $campaign->missed_count,
            'answer_rate'    => $campaign->dialed_count > 0
                                ? round(($campaign->answered_count / $campaign->dialed_count) * 100, 2) . '%'
                                : '0%',
            'generated_at'   => now()->toISOString(),
        ];

        Log::info("Campaign report generated", $report);

        // You can extend this to email the report, save to storage, etc.
        \Illuminate\Support\Facades\Storage::put(
            "mikopbx/campaigns/report-{$this->campaignId}.json",
            json_encode($report, JSON_PRETTY_PRINT)
        );
    }
}
