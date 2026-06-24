<?php

namespace BitDreamIT\MikoPBX\Services;

use BitDreamIT\MikoPBX\Models\Extension;

class AgentService
{
    public function __construct(private RestApiService $api) {}

    /** Get all agents with live SIP status merged */
    public function all(): \Illuminate\Database\Eloquent\Collection
    {
        $agents  = Extension::orderBy('extension')->get();
        $statuses = collect($this->api->getExtensionStatuses()['data'] ?? [])
            ->keyBy('id');

        return $agents->map(function ($agent) use ($statuses) {
            $live = $statuses->get($agent->sip_peer ?? $agent->extension);
            if ($live) {
                $agent->status = match($live['state'] ?? '') {
                    'REACHABLE', 'OK' => $agent->status === 'busy' ? 'busy' : 'online',
                    'UNREACHABLE', 'UNKNOWN' => 'offline',
                    default => $agent->status,
                };
            }
            return $agent;
        });
    }

    /** Sync extensions from MikoPBX into local DB */
    public function sync(): int
    {
        $remoteExtensions = $this->api->getExtensions()['data'] ?? [];
        $count = 0;

        foreach ($remoteExtensions as $ext) {
            Extension::updateOrCreate(
                ['extension' => $ext['value'] ?? $ext['number'] ?? ''],
                [
                    'name'     => $ext['name'] ?? $ext['text'] ?? 'Unknown',
                    'sip_peer' => $ext['value'] ?? null,
                    'active'   => true,
                ]
            );
            $count++;
        }

        return $count;
    }

    public function setStatus(string $extension, string $status): void
    {
        Extension::where('extension', $extension)->update(['status' => $status]);
    }

    public function getOnlineCount(): int
    {
        return Extension::whereIn('status', ['online', 'busy'])->count();
    }

    public function getAvailableAgents(): \Illuminate\Database\Eloquent\Collection
    {
        return Extension::where('status', 'online')->where('active', true)->get();
    }
}
