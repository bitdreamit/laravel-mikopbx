<?php

namespace BitDreamIT\MikoPBX\Services;

class AgentService
{
    public function __construct(
        private RestApiService $api,
        private AMIService     $ami
    ) {}

    // Get all agent statuses
    public function getAllStatuses(): array
    {
        return $this->api->getExtensionStatuses();
    }

    // Get specific agent status
    public function status(string $extension): string
    {
        $statuses = $this->api->getExtensionStatuses();
        $agents   = $statuses['data'] ?? [];

        $agent = collect($agents)->firstWhere('number', $extension);
        return $agent['status'] ?? 'unknown';
    }

    // Check if agent is online
    public function isOnline(string $extension): bool
    {
        return in_array($this->status($extension), ['REGISTERED', 'OK']);
    }

    // Get all online agents
    public function getOnlineAgents(): array
    {
        $statuses = $this->api->getExtensionStatuses();
        $agents   = $statuses['data'] ?? [];

        return collect($agents)
            ->filter(fn($a) => in_array($a['status'] ?? '', ['REGISTERED', 'OK']))
            ->values()
            ->toArray();
    }

    // Make agent call a customer (click to call)
    public function callCustomer(string $agentExtension, string $customerNumber): array
    {
        return $this->api->originate($agentExtension, $customerNumber);
    }

    // Transfer call to another agent
    public function transferTo(string $channel, string $targetExtension): array
    {
        return $this->api->transfer($channel, $targetExtension);
    }

    // Hangup agent call
    public function hangup(string $channel): array
    {
        return $this->api->hangup($channel);
    }

    // Get agent active calls
    public function getActiveCalls(?string $extension = null): array
    {
        $calls = $this->api->getActiveCalls();
        $data  = $calls['data'] ?? [];

        if ($extension) {
            return collect($data)
                ->filter(fn($c) => str_contains($c['channel'] ?? '', $extension))
                ->values()
                ->toArray();
        }

        return $data;
    }
}
