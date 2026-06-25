<?php

namespace BitDreamIT\MikoPBX\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use BitDreamIT\MikoPBX\Models\{CallLog, Extension};
use BitDreamIT\MikoPBX\Services\{RestApiService, AMIService};

class CallController extends Controller
{
    public function __construct(
        private RestApiService $api,
        private AMIService     $ami
    ) {}

    public function index(Request $request)
    {
        $query = CallLog::query()->latest('started_at');

        if ($s = $request->search) {
            $query->where(fn($q) => $q->where('caller', 'like', "%{$s}%")
                                      ->orWhere('callee', 'like', "%{$s}%"));
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
        $statuses   = ['answered', 'missed', 'busy', 'failed', 'ended'];

        return view('mikopbx::calls.index', compact('calls', 'extensions', 'statuses'));
    }

    public function show(CallLog $call)
    {
        return view('mikopbx::calls.show', compact('call'));
    }

    /**
     * Return currently active calls as JSON.
     * Uses GET /pbxcore/api/v3/pbx-status:getActiveCalls
     */
    public function active()
    {
        try {
            $response = $this->api->getActiveCalls();
            // v3 envelope: { result: true, data: {...} }
            $calls = $response['data'] ?? [];
            // data may be an object/array of active calls keyed by channel
            if (is_array($calls) && ! array_is_list($calls)) {
                $calls = array_values($calls);
            }
        } catch (\Throwable) {
            $calls = [];
        }
        return response()->json($calls);
    }

    /**
     * Originate a call FROM an extension TO a number.
     *
     * IMPORTANT: MikoPBX REST API v3 has NO originate endpoint.
     * This uses AMI (Asterisk Manager Interface) via TCP socket.
     * AMI Action: Originate
     */
    public function originate(Request $request)
    {
        $request->validate([
            'from' => 'required|string',   // extension number e.g. "101"
            'to'   => 'required|string',   // destination number e.g. "01711000000"
        ]);

        try {
            // Connect to AMI, originate, disconnect
            $connected = $this->ami->connect();
            if (! $connected) {
                return response()->json(['success' => false, 'message' => 'AMI connection failed. Check MIKOPBX_AMI_* settings.'], 503);
            }

            $result = $this->ami->originate($request->from, $request->to);
            $this->ami->disconnect();

            $success = ($result['Response'] ?? '') === 'Success' || ($result['Response'] ?? '') === 'Follows';

            return response()->json(['success' => $success, 'data' => $result]);

        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Transfer an active call channel to another extension.
     *
     * IMPORTANT: No REST v3 transfer endpoint.
     * Uses AMI Action: Redirect
     */
    public function transfer(Request $request)
    {
        $request->validate([
            'channel' => 'required|string',   // e.g. "PJSIP/101-00000001"
            'to'      => 'required|string',   // extension to transfer to e.g. "102"
        ]);

        try {
            $connected = $this->ami->connect();
            if (! $connected) {
                return response()->json(['success' => false, 'message' => 'AMI connection failed.'], 503);
            }

            $result = $this->ami->redirect($request->channel, $request->to);
            $this->ami->disconnect();

            return response()->json(['success' => ($result['Response'] ?? '') === 'Success', 'data' => $result]);

        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Hangup an active call channel.
     *
     * IMPORTANT: No REST v3 hangup endpoint.
     * Uses AMI Action: Hangup
     */
    public function hangup(Request $request)
    {
        $request->validate(['channel' => 'required|string']);

        try {
            $connected = $this->ami->connect();
            if (! $connected) {
                return response()->json(['success' => false, 'message' => 'AMI connection failed.'], 503);
            }

            $result = $this->ami->hangup($request->channel);
            $this->ami->disconnect();

            return response()->json(['success' => ($result['Response'] ?? '') === 'Success']);

        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Mute or unmute an active call channel.
     *
     * IMPORTANT: No REST v3 mute endpoint.
     * Uses AMI Action: MuteAudio
     */
    public function mute(Request $request)
    {
        $request->validate(['channel' => 'required|string']);

        try {
            $connected = $this->ami->connect();
            if (! $connected) {
                return response()->json(['success' => false, 'message' => 'AMI connection failed.'], 503);
            }

            $mute   = (bool) $request->input('mute', true);
            $result = $mute
                ? $this->ami->mute($request->channel)
                : $this->ami->unmute($request->channel);

            $this->ami->disconnect();

            return response()->json(['success' => true, 'muted' => $mute]);

        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
