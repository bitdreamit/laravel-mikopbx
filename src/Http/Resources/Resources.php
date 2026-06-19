<?php

namespace BitDreamIT\MikoPBX\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use BitDreamIT\MikoPBX\Traits\FormatsCallDuration;

/**
 * CallLogResource
 */
class CallLogResource extends JsonResource
{
    use FormatsCallDuration;

    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'caller'         => $this->caller,
            'caller_name'    => $this->caller_name,
            'extension'      => $this->extension,
            'direction'      => $this->direction,
            'status'         => $this->status,
            'status_label'   => ucfirst($this->status),
            'duration'       => $this->duration,
            'duration_human' => $this->formatDuration($this->duration ?? 0),
            'cause'          => $this->cause,
            'recording_file' => $this->recording_file,
            'recording_url'  => $this->recording_file
                ? url('/mikopbx/recordings/' . $this->recording_file . '/download')
                : null,
            'wait_time'      => $this->started_at && $this->answered_at
                ? $this->started_at->diffInSeconds($this->answered_at)
                : null,
            'started_at'     => $this->started_at?->toISOString(),
            'answered_at'    => $this->answered_at?->toISOString(),
            'ended_at'       => $this->ended_at?->toISOString(),
            'started_at_human' => $this->started_at?->diffForHumans(),
        ];
    }
}

/**
 * CallLogCollection
 */
class CallLogCollection extends ResourceCollection
{
    public $collects = CallLogResource::class;
}

/**
 * CampaignResource
 */
class CampaignResource extends JsonResource
{
    public function toArray($request): array
    {
        $answerRate = $this->dialed_count > 0
            ? round(($this->answered_count / $this->dialed_count) * 100, 1)
            : 0;

        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'type'           => $this->type,
            'status'         => $this->status,
            'max_channels'   => $this->max_channels,
            'total_numbers'  => $this->total_numbers,
            'dialed_count'   => $this->dialed_count,
            'answered_count' => $this->answered_count,
            'missed_count'   => $this->missed_count,
            'failed_count'   => $this->failed_count,
            'answer_rate'    => $answerRate,
            'progress'       => $this->total_numbers > 0
                ? round(($this->dialed_count / $this->total_numbers) * 100, 1)
                : 0,
            'scheduled_at'   => $this->scheduled_at?->toISOString(),
            'started_at'     => $this->started_at?->toISOString(),
            'stopped_at'     => $this->stopped_at?->toISOString(),
            'finished_at'    => $this->finished_at?->toISOString(),
            'created_at'     => $this->created_at?->toISOString(),
        ];
    }
}

/**
 * AgentResource
 */
class AgentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'extension'     => $this->extension ?? $this['number'] ?? '',
            'name'          => $this->name       ?? $this['name']  ?? '',
            'department'    => $this->department  ?? '',
            'status'        => $this->status      ?? $this['status'] ?? 'UNREACHABLE',
            'online'        => $this->online      ?? in_array($this['status'] ?? '', ['REGISTERED', 'OK']),
            'status_label'  => $this->getStatusLabel(),
            'current_call'  => $this->current_channel ?? null,
            'last_seen_at'  => isset($this->last_seen_at) ? $this->last_seen_at?->diffForHumans() : null,
        ];
    }

    private function getStatusLabel(): string
    {
        return match($this->status ?? $this['status'] ?? '') {
            'REGISTERED', 'OK' => '🟢 Online',
            'INUSE'            => '🟡 In Call',
            'RINGING'          => '🔵 Ringing',
            'UNREACHABLE'      => '🔴 Offline',
            default            => '⚫ Unknown',
        };
    }
}

/**
 * AnalyticsDashboardResource
 */
class AnalyticsDashboardResource extends JsonResource
{
    public function toArray($request): array
    {
        return array_merge($this->resource, [
            'answer_rate_label'  => ($this->resource['answer_rate'] ?? 0) . '%',
            'miss_rate_label'    => ($this->resource['miss_rate']   ?? 0) . '%',
            'avg_handle_time_human' => gmdate('i:s', $this->resource['avg_handle_time'] ?? 0),
            'generated_at'       => now()->toISOString(),
        ]);
    }
}
