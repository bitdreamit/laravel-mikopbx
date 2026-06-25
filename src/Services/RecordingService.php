<?php

namespace BitDreamIT\MikoPBX\Services;

use Illuminate\Support\Facades\Http;
use BitDreamIT\MikoPBX\Models\CallLog;

class RecordingService
{
    public function __construct(private RestApiService $api) {}

    /**
     * List CDR records that have recordings.
     * Uses GET /pbxcore/api/v3/cdr with date/number filters.
     * Only returns records with a recordingfile or playback_url.
     */
    public function list(string $from, string $to, string $number = ''): array
    {
        return $this->api->getRecordings($from, $to, $number);
    }

    /**
     * Proxy stream a recording from MikoPBX through Laravel.
     * Accepts either a CDR record ID (integer) or a filename string.
     *
     * The MikoPBX v3 API returns pre-signed playback_url in each CDR record.
     * Use that URL directly when available.
     */
    public function proxyStream(string $filenameOrId): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        // Try to find the CDR record to get the pre-signed URL
        $playbackUrl = null;

        if (is_numeric($filenameOrId)) {
            try {
                $cdr = $this->api->getCDRById((int) $filenameOrId);
                $playbackUrl = $cdr['data']['playback_url'] ?? null;
            } catch (\Throwable) {}
        }

        // Fall back to local DB lookup by recording_file
        if (! $playbackUrl) {
            $log = CallLog::where('recording_file', $filenameOrId)->first();
            $playbackUrl = $log?->recording_url;
        }

        // Last resort: build URL from the legacy endpoint
        if (! $playbackUrl) {
            $playbackUrl = $this->api->getRecordingUrl($filenameOrId);
        }

        $apiKey = config('mikopbx.api_key');

        return response()->stream(function () use ($playbackUrl, $apiKey) {
            $stream = Http::withHeaders(['Authorization' => "Bearer {$apiKey}"])
                ->withoutVerifying()
                ->get($playbackUrl)
                ->toPsrResponse()
                ->getBody();

            while (! $stream->eof()) {
                echo $stream->read(8192);
                flush();
            }
        }, 200, [
            'Content-Type'        => 'audio/wav',
            'Content-Disposition' => "inline; filename=\"{$filenameOrId}\"",
            'Cache-Control'       => 'no-cache',
        ]);
    }

    /**
     * Get a proxied URL for a recording — routes through Laravel for auth.
     */
    public function getSignedUrl(string $filenameOrId): string
    {
        return route('mikopbx.recordings.play', ['filename' => $filenameOrId]);
    }

    /**
     * Get the direct MikoPBX playback URL for a CDR record.
     * Uses the pre-signed playback_url if available.
     */
    public function getDirectUrl(int $cdrId): string
    {
        return $this->api->getRecordingPlaybackUrl($cdrId);
    }
}
