<?php

namespace BitDreamIT\MikoPBX\Services;

use BitDreamIT\MikoPBX\Models\Campaign;
use BitDreamIT\MikoPBX\Models\CampaignNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;

class CampaignService
{
    public function __construct(private RestApiService $api) {}

    /** Create campaign in DB and push to MikoPBX */
    public function create(array $data, array $numbers, UploadedFile|string|null $audio = null): Campaign
    {
        return DB::transaction(function () use ($data, $numbers, $audio) {

            $campaign = Campaign::create([
                'name'              => $data['name'],
                'type'              => $data['type'] ?? 'agent_connect',
                'status'            => 'draft',
                'max_channels'      => $data['max_channels'] ?? 5,
                'retry_attempts'    => $data['retry_attempts'] ?? 3,
                'retry_delay'       => $data['retry_delay'] ?? 300,
                'dial_timeout'      => $data['dial_timeout'] ?? 30,
                'caller_id'         => $data['caller_id'] ?? null,
                'destination_extension' => $data['destination_extension'] ?? null,
                'scheduled_at'      => $data['scheduled_at'] ?? null,
                'created_by'        => auth()->id(),
                'total_numbers'     => count($numbers),
            ]);

            // Bulk insert numbers
            $rows = array_map(fn($n) => [
                'campaign_id' => $campaign->id,
                'number'      => is_array($n) ? $n['number'] : $n,
                'name'        => is_array($n) ? ($n['name'] ?? null) : null,
                'status'      => 'pending',
                'created_at'  => now(),
                'updated_at'  => now(),
            ], $numbers);

            CampaignNumber::insert($rows);

            // Upload audio if provided
            if ($audio) {
                $path = is_string($audio) ? $audio : $audio->getPathname();
                $result = $this->api->uploadAudio($path);
                $campaign->update(['audio_file' => $result['filename'] ?? null]);
            }

            return $campaign;
        });
    }

    /** Start a campaign — push task to MikoPBX */
    public function start(Campaign $campaign): Campaign
    {
        $numbers = $campaign->numbers()
            ->where('status', 'pending')
            ->select('number')
            ->get()
            ->map(fn($n) => ['number' => $n->number])
            ->toArray();

        $taskData = [
            'name'             => $campaign->name,
            'state'            => 0,
            'innerNumType'     => $campaign->type === 'voice_broadcast' ? 'audio' : 'polling',
            'maxCountChannels' => $campaign->max_channels,
            'dialPrefix'       => '',
            'numbers'          => $numbers,
        ];

        if ($campaign->destination_extension) {
            $taskData['innerNum'] = $campaign->destination_extension;
        }

        $result = $this->api->createDialerTask($taskData);
        $taskId = $result['id'] ?? $result['data']['id'] ?? null;

        if ($taskId) {
            $this->api->startDialerTask((int) $taskId);
        }

        $campaign->update([
            'mikopbx_task_id' => $taskId,
            'status'          => 'running',
            'started_at'      => now(),
        ]);

        return $campaign->fresh();
    }

    public function pause(Campaign $campaign): Campaign
    {
        if ($campaign->mikopbx_task_id) {
            $this->api->pauseDialerTask($campaign->mikopbx_task_id);
        }
        $campaign->update(['status' => 'paused']);
        return $campaign->fresh();
    }

    public function stop(Campaign $campaign): Campaign
    {
        if ($campaign->mikopbx_task_id) {
            $this->api->stopDialerTask($campaign->mikopbx_task_id);
        }
        $campaign->update(['status' => 'completed', 'completed_at' => now()]);
        return $campaign->fresh();
    }

    /** Sync campaign progress from MikoPBX */
    public function syncProgress(Campaign $campaign): array
    {
        if (! $campaign->mikopbx_task_id) return [];

        $status = $this->api->getDialerTaskStatus($campaign->mikopbx_task_id);

        $campaign->update([
            'dialed'   => $status['dialed']   ?? $campaign->dialed,
            'answered' => $status['answered']  ?? $campaign->answered,
            'failed'   => $status['failed']    ?? $campaign->failed,
        ]);

        return $status;
    }

    /** Parse CSV/text file into number array */
    public function parseNumbersFromFile(UploadedFile $file): array
    {
        $numbers = [];
        $content = file($file->getPathname(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($content as $line) {
            $parts  = str_getcsv($line);
            $number = preg_replace('/\D/', '', trim($parts[0] ?? ''));
            $name   = trim($parts[1] ?? '');

            if (strlen($number) >= 7) {
                $numbers[] = ['number' => $number, 'name' => $name ?: null];
            }
        }

        return $numbers;
    }

    public function getStats(Campaign $campaign): array
    {
        $total = $campaign->total_numbers ?: 1;
        return [
            'total'    => $campaign->total_numbers,
            'dialed'   => $campaign->dialed,
            'answered' => $campaign->answered,
            'failed'   => $campaign->failed,
            'pending'  => $campaign->total_numbers - $campaign->dialed,
            'progress' => round(($campaign->dialed / $total) * 100, 1),
            'asr'      => $campaign->dialed > 0 ? round(($campaign->answered / $campaign->dialed) * 100, 1) : 0,
        ];
    }
}
