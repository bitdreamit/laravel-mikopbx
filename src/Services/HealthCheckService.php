<?php

namespace BitDreamIT\MikoPBX\Services;

use BitDreamIT\MikoPBX\Models\HealthLog;

class HealthCheckService
{
	public function __construct(
		private RestApiService $api,
		private AMIService     $ami
	) {}

	public function check(): array
	{
		$amiOk         = false;
		$ariOk         = false;
		$sipOk         = false;
		$calls         = 0;
		$onlineCount   = 0;
		$onlineNumbers = '';

		// 1. AMI check
		try {
			if ($this->ami->connect()) {
				$amiOk = true;
				$this->ami->disconnect();
			}
		} catch (\Throwable) {}

		// 2. REST API reachability
		try {
			$info  = $this->api->getSystemInfo();
			$ariOk = ($info['result'] ?? false) === true;
		} catch (\Throwable) {}

		// 3. SIP trunk registration
		try {
			$trunk     = $this->api->getTrunkStatus();
			$data      = $trunk['data'] ?? [];
			$allTrunks = array_merge(
				array_values($data['sip'] ?? []),
				array_values($data['iax'] ?? [])
			);
			$sipOk = collect($allTrunks)->contains(
				fn($t) => strtoupper($t['state'] ?? '') === 'REGISTERED'
			);
		} catch (\Throwable) {}

		// 4. Active calls count
		try {
			$active = $this->api->getActiveCalls();
			$data   = $active['data'] ?? [];
			$calls  = is_array($data) ? count($data) : 0;
		} catch (\Throwable) {}

		// 5. Online extensions from MikoPBX API
		try {
			$peers  = $this->api->getExtensionStatuses();
			$online = collect($peers['data'] ?? [])
				->filter(fn($e) =>
					$e['state'] === 'OK' &&
					!str_contains($e['id'], '-TLS') &&
					!str_contains($e['id'], '-WS') &&
					!str_starts_with($e['id'], 'SIP-TRUNK-')
				)
				->values();

			$onlineCount   = $online->count();
			$onlineNumbers = $online->pluck('id')->join(', ');
		} catch (\Throwable) {}

		$status = match(true) {
			!$amiOk && !$ariOk => 'critical',
			!$sipOk            => 'degraded',
			default            => 'healthy',
		};

		$result = [
			'amiOk'          => $amiOk,
			'ariOk'          => $ariOk,
			'sipOk'          => $sipOk,
			'calls'          => $calls,
			'online'         => $onlineCount,
			'online_numbers' => $onlineNumbers,
			'status'         => $status,
		];

		HealthLog::create([
			'status'            => $status,
			'ami_connected'     => $amiOk,
			'ari_connected'     => $ariOk,
			'sip_trunk_up'      => $sipOk,
			'active_calls'      => $calls,
			'extensions_online' => $onlineCount,
			'details'           => $result,
			'checked_at'        => now(),
		]);

		return $result;
	}

	public function latest(): ?HealthLog
	{
		return HealthLog::latest('checked_at')->first();
	}

	public function history(int $hours = 24): \Illuminate\Database\Eloquent\Collection
	{
		return HealthLog::where('checked_at', '>=', now()->subHours($hours))
			->orderBy('checked_at')
			->get();
	}
}