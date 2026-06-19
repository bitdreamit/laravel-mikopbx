<?php

namespace BitDreamIT\MikoPBX\Services;

use Illuminate\Support\Facades\Storage;

class RecordingService
{
    public function __construct(
        private RestApiService $api,
        private ARIService     $ari,
        private array          $config
    ) {}

    // ─────────────────────────────────────────
    // LIST & SEARCH RECORDINGS
    // ─────────────────────────────────────────

    public function getAll(string $dateFrom, string $dateTo, ?string $extension = null): array
    {
        return $this->api->getRecordings($dateFrom, $dateTo, $extension);
    }

    public function getToday(?string $extension = null): array
    {
        return $this->getAll(today()->toDateString(), today()->toDateString(), $extension);
    }

    public function getThisMonth(?string $extension = null): array
    {
        return $this->getAll(
            now()->startOfMonth()->toDateString(),
            now()->endOfMonth()->toDateString(),
            $extension
        );
    }

    // ─────────────────────────────────────────
    // DOWNLOAD
    // ─────────────────────────────────────────

    public function getDownloadUrl(string $filename): string
    {
        return $this->api->getRecordingUrl($filename);
    }

    public function download(string $filename, string $localPath): bool
    {
        $url      = $this->getDownloadUrl($filename);
        $contents = file_get_contents($url);

        if ($contents === false) return false;

        return Storage::put($localPath, $contents);
    }

    // ─────────────────────────────────────────
    // LIVE RECORDING (via ARI)
    // ─────────────────────────────────────────

    public function startLiveRecording(string $channelId, string $name, string $format = 'wav'): array
    {
        return $this->ari->startRecording($channelId, $name, $format);
    }

    public function stopLiveRecording(string $recordingName): array
    {
        return $this->ari->stopRecording($recordingName);
    }

    public function pauseLiveRecording(string $recordingName): array
    {
        return $this->ari->pauseRecording($recordingName);
    }

    public function resumeLiveRecording(string $recordingName): array
    {
        return $this->ari->resumeRecording($recordingName);
    }

    // ─────────────────────────────────────────
    // STORED RECORDINGS
    // ─────────────────────────────────────────

    public function getStoredRecordings(): array
    {
        return $this->ari->getStoredRecordings();
    }

    public function deleteStoredRecording(string $name): array
    {
        return $this->ari->deleteRecording($name);
    }

    // ─────────────────────────────────────────
    // STATS
    // ─────────────────────────────────────────

    public function getStats(string $dateFrom, string $dateTo): array
    {
        $records = $this->getAll($dateFrom, $dateTo);
        $data    = $records['data'] ?? [];

        return [
            'total'          => count($data),
            'total_duration' => collect($data)->sum('duration'),
            'avg_duration'   => collect($data)->avg('duration'),
            'by_extension'   => collect($data)->groupBy('extension')->map->count(),
            'by_date'        => collect($data)->groupBy(fn($r) => substr($r['start'] ?? '', 0, 10))->map->count(),
        ];
    }
}
