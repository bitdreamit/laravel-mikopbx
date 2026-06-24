<?php

namespace BitDreamIT\MikoPBX\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use BitDreamIT\MikoPBX\Models\{CallLog, Extension};
use BitDreamIT\MikoPBX\Services\RestApiService;

class CallController extends Controller
{
    public function __construct(private RestApiService $api) {}

    public function index(Request $request)
    {
        $query = CallLog::query()->latest('started_at');

        if ($s = $request->search) {
            $query->where(fn($q) => $q->where('caller','like',"%{$s}%")->orWhere('callee','like',"%{$s}%"));
        }
        if ($f = $request->filter) {
            $query->where('status', $f);
        }
        if ($d = $request->date) {
            $query->whereDate('started_at', $d);
        }
        if ($e = $request->extension) {
            $query->where('extension', $e);
        }
        if ($dir = $request->direction) {
            $query->where('direction', $dir);
        }

        $calls      = $query->paginate(25)->withQueryString();
        $extensions = Extension::orderBy('extension')->get();
        $statuses   = ['answered','missed','busy','failed','ended'];

        return view('mikopbx::calls.index', compact('calls','extensions','statuses'));
    }

    public function show(CallLog $call)
    {
        return view('mikopbx::calls.show', compact('call'));
    }

    public function active()
    {
        try {
            $calls = $this->api->getActiveCalls()['data'] ?? [];
        } catch (\Throwable) {
            $calls = [];
        }
        return response()->json($calls);
    }

    public function originate(Request $request)
    {
        $request->validate([
            'from' => 'required|string',
            'to'   => 'required|string',
        ]);

        try {
            $result = $this->api->originate($request->from, $request->to);
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function transfer(Request $request)
    {
        $request->validate(['channel' => 'required', 'to' => 'required']);

        try {
            $result = $this->api->transfer($request->channel, $request->to);
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function hangup(Request $request)
    {
        $request->validate(['channel' => 'required']);

        try {
            $result = $this->api->hangup($request->channel);
            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function mute(Request $request)
    {
        $request->validate(['channel' => 'required']);

        try {
            $result = $this->api->mute($request->channel, (bool) $request->mute);
            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
