<?php
namespace BitDreamIT\MikoPBX\Services;
use Illuminate\Support\Facades\Http;

class RecordingService
{
    public function __construct(private RestApiService $api) {}

    public function list(string $from, string $to, string $number = ''): array
    {
        return $this->api->getRecordings($from, $to, $number);
    }

    public function proxyStream(string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $url = $this->api->getRecordingUrl($filename);
        return response()->stream(function () use ($url) {
            $stream = fopen($url, 'r');
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type'        => 'audio/wav',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }

    public function getSignedUrl(string $filename): string
    {
        return route('mikopbx.recordings.play', ['filename' => $filename]);
    }
}
