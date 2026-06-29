<?php

namespace BitDreamIT\MikoPBX\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use BitDreamIT\MikoPBX\Services\RecordingService;

class RecordingController extends Controller
{
    public function __construct(private RecordingService $svc) {}

    public function index(Request $request)
    {
        $from = $request->from ?? now()->subDays(7)->format('Y-m-d');
        $to   = $request->to   ?? now()->format('Y-m-d');
        $num  = $request->number ?? '';

        try {
            $result     = $this->svc->list($from, $to, $num);
            $recordings = $result['data'] ?? [];
        } catch (\Throwable $e) {
            $recordings = [];
            session()->flash('error', 'Could not load recordings: ' . $e->getMessage());
        }

        return view('mikopbx::recordings.index', compact('recordings', 'from', 'to', 'num'));
    }

    /**
     * Proxy stream a recording from MikoPBX through Laravel.
     *
     * The `filename` parameter can be:
     *   1. A relative playback_url: /pbxcore/api/v3/cdr:playback?token=abc123
     *   2. A full URL:              https://pbx.htncr.org/pbxcore/api/v3/...
     *   3. A legacy filename:       mikopbx-xxx.webm
     *
     * This proxy adds Bearer auth so the browser never needs the API key.
     * It also sets correct audio headers for streaming.
     */
    public function play(Request $request)
    {
        $filename = $request->input('filename', '');

        if (empty($filename)) {
            abort(404, 'No filename specified');
        }

        // URL-decode in case it was double-encoded
        $filename = urldecode($filename);

        $baseUrl = rtrim(config('mikopbx.url', ''), '/');
        $apiKey  = config('mikopbx.api_key', '');


        // Build the full URL to fetch from MikoPBX
        if (str_starts_with($filename, '/pbxcore/')) {
            // Relative playback_url from API e.g. /pbxcore/api/v3/cdr:playback?token=xxx
            $fetchUrl = $baseUrl . $filename;

        } elseif (str_starts_with($filename, 'http')) {
            // Already absolute
            $fetchUrl = $filename;

        } else {
            // Legacy filename — build old-style URL
            $fetchUrl = $baseUrl . '/pbxcore/api/v3/cdr:playback?filename=' . urlencode($filename);
        }

        // Determine content type from file extension
        $ext = strtolower(pathinfo(parse_url($fetchUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
        $contentType = match($ext) {
            'webm'  => 'audio/webm',
            'mp3'   => 'audio/mpeg',
            'wav'   => 'audio/wav',
            'ogg'   => 'audio/ogg',
            default => 'audio/webm',   // MikoPBX default recording format
        };

        try {
            // Fetch the audio file from MikoPBX with Bearer auth
            $mikoPBXResponse = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
            ])
            ->withoutVerifying()
            ->timeout(30)
            ->get($fetchUrl);

            if ($mikoPBXResponse->failed()) {
                abort($mikoPBXResponse->status(), 'Recording not available');
            }

            $audioBody = $mikoPBXResponse->body();

            return response($audioBody, 200, [
                'Content-Type'        => $contentType,
                'Content-Length'      => strlen($audioBody),
                'Content-Disposition' => 'inline; filename="recording.' . ($ext ?: 'webm') . '"',
                'Accept-Ranges'       => 'bytes',
                'Cache-Control'       => 'no-cache, no-store',
            ]);

        } catch (\Throwable $e) {
            abort(500, 'Error streaming recording: ' . $e->getMessage());
        }
    }
}
