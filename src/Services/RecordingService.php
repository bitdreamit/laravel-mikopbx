<?php

namespace BitDreamIT\MikoPBX\Services;

use Illuminate\Support\Facades\Http;
use BitDreamIT\MikoPBX\Models\CallLog;

class RecordingService
{
    public function __construct(private RestApiService $api) {}

    /**
     * List recordings — returns flattened inner channel records that have playback_url.
     *
     * Real response field names from actual API:
     *   src_num, dst_num  — caller/callee numbers
     *   start             — call start time
     *   billsec           — answered duration in seconds
     *   playback_url      — "/pbxcore/api/v3/cdr:playback?token=abc123"
     *   download_url      — "/pbxcore/api/v3/cdr:download?token=abc123"
     *   recordingfile     — full server path e.g. "/storage/usbdisk1/.../.webm"
     *   UNIQUEID          — unique call identifier
     */
    public function list(string $from, string $to, string $number = ''): array
    {
        return $this->api->getRecordings($from, $to, $number);
    }

    /**
     * Build the full playback URL.
     *
     * The API returns relative URLs like:
     *   /pbxcore/api/v3/cdr:playback?token=ac95731c81a42a13fc28a8d8de48a594
     *
     * We prepend the MikoPBX base URL to make it absolute.
     *
     * @param string $relativeUrl  The playback_url from the CDR record
     */
    public function buildPlaybackUrl(string $relativeUrl): string
    {
        if (empty($relativeUrl)) return '';

        // Already absolute
        if (str_starts_with($relativeUrl, 'http')) return $relativeUrl;

        $base = rtrim(config('mikopbx.url', ''), '/');
        return $base . $relativeUrl;
    }

    /**
     * Proxy-stream a recording through Laravel.
     * Uses the token-based playback_url returned by the API.
     * Accepts either:
     *   - A relative URL:  /pbxcore/api/v3/cdr:playback?token=xxx
     *   - A full URL:      https://pbx.htncr.org/pbxcore/api/v3/cdr:playback?token=xxx
     *   - A legacy filename: mikopbx-xxx.webm
     */
    public function proxyStream(string $urlOrFilename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        // Determine the full URL to fetch from MikoPBX
        if (str_starts_with($urlOrFilename, '/pbxcore/')) {
            // Relative playback_url from API — just prepend base URL
            $fullUrl = rtrim(config('mikopbx.url', ''), '/') . $urlOrFilename;
        } elseif (str_starts_with($urlOrFilename, 'http')) {
            $fullUrl = $urlOrFilename;
        } else {
            // Legacy filename — try to find in local DB
            $log = CallLog::where('recording_file', $urlOrFilename)->first();
            if ($log?->recording_url) {
                $fullUrl = $this->buildPlaybackUrl($log->recording_url);
            } else {
                // Fallback: try the old-style URL
                $fullUrl = rtrim(config('mikopbx.url', ''), '/') .
                           '/pbxcore/api/v3/cdr:playback?filename=' . urlencode($urlOrFilename);
            }
        }

        $apiKey = config('mikopbx.api_key');

        return response()->stream(function () use ($fullUrl, $apiKey) {
            $response = Http::withHeaders(['Authorization' => "Bearer {$apiKey}"])
                ->withoutVerifying()
                ->timeout(60)
                ->get($fullUrl);

            echo $response->body();
            flush();
        }, 200, [
            'Content-Type'        => 'audio/webm, audio/wav, audio/*',
            'Content-Disposition' => 'inline; filename="recording.webm"',
            'Cache-Control'       => 'no-cache',
            'Accept-Ranges'       => 'bytes',
        ]);
    }

    /**
     * Get proxied URL routing through Laravel for a recording.
     * Accepts relative playback_url or filename.
     */
    public function getSignedUrl(string $urlOrFilename): string
    {
        return route('mikopbx.recordings.play', ['filename' => urlencode($urlOrFilename)]);
    }
}
