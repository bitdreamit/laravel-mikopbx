<?php

namespace BitDreamIT\MikoPBX\Services;

use BitDreamIT\MikoPBX\Models\Campaign;
use BitDreamIT\MikoPBX\Models\CampaignNumber;

class CampaignService
{
    public function __construct(
        private RestApiService $api,
        private array $config
    ) {}

    // ─────────────────────────────────────────
    // CREATE CAMPAIGN
    // ─────────────────────────────────────────

    public function create(
        string $name,
        array  $numbers,
        string $audioFile,
        int    $maxChannels = 5,
        array  $ivrOptions  = []
    ): Campaign {

        // 1. Upload audio file to MikoPBX
        $audioResponse = $this->api->uploadAudio($audioFile);
        $audioId       = $audioResponse['data']['id'] ?? null;

        // 2. Create IVR polling script if options provided
        $pollingId = null;
        if (!empty($ivrOptions)) {
            $pollingResponse = $this->api->createPolling([
                'name'      => $name . ' IVR',
                'questions' => $ivrOptions,
            ]);
            $pollingId = $pollingResponse['data']['id'] ?? null;
        }

        // 3. Create campaign on MikoPBX
        $taskData = [
            'name'             => $name,
            'state'            => 0,
            'innerNumType'     => $pollingId ? 'polling' : 'audio',
            'innerNum'         => $pollingId ?? $audioId,
            'maxCountChannels' => $maxChannels,
            'numbers'          => collect($numbers)
                                    ->map(fn($n) => ['number' => $n])
                                    ->toArray(),
        ];

        $taskResponse = $this->api->createDialerTask($taskData);
        $mikoPBXId    = $taskResponse['data']['id'] ?? null;

        // 4. Save to local DB
        $campaign = Campaign::create([
            'name'            => $name,
            'mikopbx_task_id' => $mikoPBXId,
            'audio_file'      => $audioFile,
            'max_channels'    => $maxChannels,
            'status'          => 'created',
            'total_numbers'   => count($numbers),
        ]);

        // 5. Save numbers
        foreach ($numbers as $number) {
            CampaignNumber::create([
                'campaign_id' => $campaign->id,
                'number'      => $number,
                'status'      => 'pending',
            ]);
        }

        return $campaign;
    }

    // ─────────────────────────────────────────
    // START / STOP
    // ─────────────────────────────────────────

    public function start(Campaign $campaign): Campaign
    {
        $this->api->startDialerTask($campaign->mikopbx_task_id);
        $campaign->update(['status' => 'running', 'started_at' => now()]);
        return $campaign->refresh();
    }

    public function stop(Campaign $campaign): Campaign
    {
        $this->api->stopDialerTask($campaign->mikopbx_task_id);
        $campaign->update(['status' => 'stopped', 'stopped_at' => now()]);
        return $campaign->refresh();
    }

    // ─────────────────────────────────────────
    // STATUS
    // ─────────────────────────────────────────

    public function status(Campaign $campaign): array
    {
        $remote = $this->api->getDialerTaskStatus($campaign->mikopbx_task_id);

        // Sync status to local DB
        $campaign->update([
            'status'          => $remote['data']['state'] ?? $campaign->status,
            'dialed_count'    => $remote['data']['dialed'] ?? $campaign->dialed_count,
            'answered_count'  => $remote['data']['answered'] ?? $campaign->answered_count,
        ]);

        return [
            'campaign' => $campaign->fresh(),
            'remote'   => $remote,
        ];
    }

    // ─────────────────────────────────────────
    // SIMPLE VOICE BROADCAST (no IVR)
    // ─────────────────────────────────────────

    public function broadcast(string $name, array $numbers, string $audioFile, int $maxChannels = 5): Campaign
    {
        return $this->create($name, $numbers, $audioFile, $maxChannels, []);
    }

    // ─────────────────────────────────────────
    // CAMPAIGN WITH IVR (Press 1 / Press 2)
    // ─────────────────────────────────────────

    public function withIVR(string $name, array $numbers, string $audioFile, array $keypressActions): Campaign
    {
        $ivrOptions = [
            [
                'questionId'   => '1',
                'questionText' => 'main',
                'press'        => collect($keypressActions)
                    ->map(fn($action, $key) => [
                        'key'    => (string) $key,
                        'action' => $action['action'],
                        'value'  => $action['value'],
                    ])->values()->toArray(),
            ]
        ];

        return $this->create($name, $numbers, $audioFile, 5, $ivrOptions);
    }
}
