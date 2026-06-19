<?php

namespace BitDreamIT\MikoPBX\Contracts;

/**
 * Contracts (Interfaces) for all MikoPBX services.
 * Allows swapping implementations in tests or custom builds.
 */

interface CallServiceContract
{
    public function originate(string $from, string $to): array;
    public function transfer(string $channel, string $extension): array;
    public function hangup(string $channel): array;
    public function getActiveCalls(): array;
    public function getExtensionStatuses(): array;
    public function getRecordings(string $dateFrom, string $dateTo, ?string $extension): array;
}

interface AMIServiceContract
{
    public function connect(): static;
    public function disconnect(): void;
    public function isConnected(): bool;
    public function originate(string $channel, string $extension, string $context, string $callerId, int $timeout, array $vars): array;
    public function blindTransfer(string $channel, string $ext, string $ctx): array;
    public function attendedTransfer(string $ch1, string $ch2): array;
    public function hangup(string $channel, int $cause): array;
    public function mute(string $channel, string $direction): array;
    public function unmute(string $channel, string $direction): array;
    public function queueAdd(string $queue, string $interface, string $memberName, bool $paused): array;
    public function queueRemove(string $queue, string $interface): array;
    public function queuePause(string $queue, string $interface, string $reason): array;
    public function queueUnpause(string $queue, string $interface): array;
    public function ping(): bool;
    public function on(string $event, callable $cb): static;
    public function listen(): void;
}

interface CampaignServiceContract
{
    public function create(string $name, array $numbers, string $audioFile, int $maxChannels, array $ivrOptions): \BitDreamIT\MikoPBX\Models\Campaign;
    public function start(\BitDreamIT\MikoPBX\Models\Campaign $campaign): \BitDreamIT\MikoPBX\Models\Campaign;
    public function stop(\BitDreamIT\MikoPBX\Models\Campaign $campaign): \BitDreamIT\MikoPBX\Models\Campaign;
    public function status(\BitDreamIT\MikoPBX\Models\Campaign $campaign): array;
    public function broadcast(string $name, array $numbers, string $audioFile, int $maxChannels): \BitDreamIT\MikoPBX\Models\Campaign;
}

interface AgentServiceContract
{
    public function getAllStatuses(): array;
    public function status(string $extension): string;
    public function isOnline(string $extension): bool;
    public function getOnlineAgents(): array;
    public function callCustomer(string $agentExtension, string $customerNumber): array;
    public function transferTo(string $channel, string $targetExtension): array;
    public function hangup(string $channel): array;
    public function getActiveCalls(?string $extension): array;
}

interface AnalyticsServiceContract
{
    public function dashboard(string $dateFrom, string $dateTo): array;
    public function peakHours(string $dateFrom, string $dateTo): array;
    public function dailyTrend(string $dateFrom, string $dateTo): array;
    public function agentPerformance(string $dateFrom, string $dateTo): array;
    public function slaCompliance(string $dateFrom, string $dateTo, int $slaSeconds): array;
    public function abandonedCalls(string $dateFrom, string $dateTo): array;
    public function exportCsv(string $dateFrom, string $dateTo): string;
}

interface BlacklistServiceContract
{
    public function block(string $number, string $reason, ?string $expiresAt): \BitDreamIT\MikoPBX\Models\Blacklist;
    public function unblock(string $number): bool;
    public function isBlocked(string $number): bool;
    public function getAll(): \Illuminate\Database\Eloquent\Collection;
}
