<?php

namespace BitDreamIT\MikoPBX\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
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
            $recordings = $this->svc->list($from, $to, $num)['data'] ?? [];
        } catch (\Throwable) {
            $recordings = [];
        }

        return view('mikopbx::recordings.index', compact('recordings','from','to','num'));
    }

    public function play(Request $request)
    {
        $filename = $request->validate(['filename' => 'required|string'])['filename'];
        return $this->svc->proxyStream($filename);
    }
}
