<?php

namespace BitDreamIT\MikoPBX\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use BitDreamIT\MikoPBX\Facades\MikoPBX;
use BitDreamIT\MikoPBX\Models\CallLog;
use BitDreamIT\MikoPBX\Models\Campaign;
use BitDreamIT\MikoPBX\Models\CampaignNumber;
use BitDreamIT\MikoPBX\Models\Extension;
use BitDreamIT\MikoPBX\Models\Callback;
use BitDreamIT\MikoPBX\Services\CampaignService;
use BitDreamIT\MikoPBX\Services\RecordingService;
use BitDreamIT\MikoPBX\Services\ConferenceService;
use BitDreamIT\MikoPBX\Services\IVRBuilder;

// ═══════════════════════════════════════════════════════════════════
// CALL CONTROLLER
// ═══════════════════════════════════════════════════════════════════

class CallController
{
    /** GET /mikopbx/calls/active */
    public function active(): JsonResponse
    {
        return response()->json(MikoPBX::call()->getActiveCalls());
    }

    /** POST /mikopbx/calls/originate */
    public function originate(Request $request): JsonResponse
    {
        $r = $request->validate([
            'from' => 'required|string',
            'to'   => 'required|string',
        ]);
        return response()->json(MikoPBX::call()->originate($r['from'], $r['to']));
    }

    /** POST /mikopbx/calls/transfer */
    public function transfer(Request $request): JsonResponse
    {
        $r = $request->validate([
            'channel'   => 'required|string',
            'extension' => 'required|string',
            'type'      => 'in:blind,attended',
        ]);

        if (($r['type'] ?? 'blind') === 'attended') {
            return response()->json(MikoPBX::ami()->attendedTransfer($r['channel'], 'PJSIP/' . $r['extension']));
        }

        return response()->json(MikoPBX::ami()->blindTransfer($r['channel'], $r['extension']));
    }

    /** POST /mikopbx/calls/hangup */
    public function hangup(Request $request): JsonResponse
    {
        $r = $request->validate(['channel' => 'required|string']);
        return response()->json(MikoPBX::ami()->hangup($r['channel']));
    }

    /** POST /mikopbx/calls/hold */
    public function hold(Request $request): JsonResponse
    {
        $r = $request->validate(['channel' => 'required|string']);
        return response()->json(MikoPBX::ami()->mute($r['channel']));
    }

    /** POST /mikopbx/calls/mute */
    public function mute(Request $request): JsonResponse
    {
        $r = $request->validate(['channel' => 'required|string', 'direction' => 'in:in,out,both']);
        return response()->json(MikoPBX::ami()->mute($r['channel'], $r['direction'] ?? 'in'));
    }

    /** POST /mikopbx/calls/unmute */
    public function unmute(Request $request): JsonResponse
    {
        $r = $request->validate(['channel' => 'required|string', 'direction' => 'in:in,out,both']);
        return response()->json(MikoPBX::ami()->unmute($r['channel'], $r['direction'] ?? 'in'));
    }

    /** POST /mikopbx/calls/park */
    public function park(Request $request): JsonResponse
    {
        $r = $request->validate(['channel' => 'required|string', 'channel2' => 'required|string']);
        return response()->json(MikoPBX::ami()->parkCall($r['channel'], $r['channel2']));
    }

    /** GET /mikopbx/calls/parked */
    public function parked(): JsonResponse
    {
        return response()->json(MikoPBX::ami()->getParkedCalls());
    }

    /** GET /mikopbx/calls/logs */
    public function logs(Request $request): JsonResponse
    {
        $logs = CallLog::query()
            ->when($request->extension,  fn($q) => $q->where('extension', $request->extension))
            ->when($request->caller,     fn($q) => $q->where('caller', 'like', "%{$request->caller}%"))
            ->when($request->status,     fn($q) => $q->where('status', $request->status))
            ->when($request->direction,  fn($q) => $q->where('direction', $request->direction))
            ->when($request->date_from,  fn($q) => $q->whereDate('started_at', '>=', $request->date_from))
            ->when($request->date_to,    fn($q) => $q->whereDate('started_at', '<=', $request->date_to))
            ->latest('started_at')
            ->paginate($request->per_page ?? 25);

        return response()->json($logs);
    }

    /** GET /mikopbx/calls/stats */
    public function stats(Request $request): JsonResponse
    {
        $from = $request->date_from ?? today()->toDateString();
        $to   = $request->date_to   ?? today()->toDateString();

        $logs = CallLog::whereBetween('started_at', [$from, $to . ' 23:59:59']);

        return response()->json([
            'total'          => $logs->count(),
            'answered'       => (clone $logs)->where('status', 'answered')->count(),
            'missed'         => (clone $logs)->where('status', 'missed')->count(),
            'avg_duration'   => (clone $logs)->where('status', 'answered')->avg('duration'),
            'total_duration' => (clone $logs)->sum('duration'),
            'inbound'        => (clone $logs)->where('direction', 'inbound')->count(),
            'outbound'       => (clone $logs)->where('direction', 'outbound')->count(),
            'by_extension'   => (clone $logs)->groupBy('extension')->selectRaw('extension, count(*) as total')->pluck('total', 'extension'),
            'by_hour'        => (clone $logs)->selectRaw('HOUR(started_at) as hour, count(*) as total')->groupBy('hour')->orderBy('hour')->pluck('total', 'hour'),
        ]);
    }
}

// ═══════════════════════════════════════════════════════════════════
// CAMPAIGN CONTROLLER
// ═══════════════════════════════════════════════════════════════════

class CampaignController
{
    public function __construct(private CampaignService $campaigns) {}

    /** GET /mikopbx/campaigns */
    public function index(): JsonResponse
    {
        return response()->json(Campaign::with('numbers')->latest()->paginate(20));
    }

    /** POST /mikopbx/campaigns */
    public function store(Request $request): JsonResponse
    {
        $r = $request->validate([
            'name'         => 'required|string|max:255',
            'numbers'      => 'required|array|min:1',
            'numbers.*'    => 'required|string',
            'audio_file'   => 'required|string',
            'max_channels' => 'integer|min:1|max:10',
            'ivr_options'  => 'nullable|array',
        ]);

        $campaign = $this->campaigns->create(
            $r['name'],
            $r['numbers'],
            $r['audio_file'],
            $r['max_channels'] ?? 5,
            $r['ivr_options']  ?? []
        );

        return response()->json($campaign, 201);
    }

    /** GET /mikopbx/campaigns/{id} */
    public function show(int $id): JsonResponse
    {
        return response()->json(Campaign::with('numbers')->findOrFail($id));
    }

    /** POST /mikopbx/campaigns/{id}/start */
    public function start(int $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);
        return response()->json($this->campaigns->start($campaign));
    }

    /** POST /mikopbx/campaigns/{id}/stop */
    public function stop(int $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);
        return response()->json($this->campaigns->stop($campaign));
    }

    /** GET /mikopbx/campaigns/{id}/status */
    public function status(int $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);
        return response()->json($this->campaigns->status($campaign));
    }

    /** DELETE /mikopbx/campaigns/{id} */
    public function destroy(int $id): JsonResponse
    {
        Campaign::findOrFail($id)->delete();
        return response()->json(['message' => 'Campaign deleted']);
    }
}

// ═══════════════════════════════════════════════════════════════════
// AGENT CONTROLLER
// ═══════════════════════════════════════════════════════════════════

class AgentController
{
    /** GET /mikopbx/agents */
    public function index(): JsonResponse
    {
        $remote     = MikoPBX::agent()->getAllStatuses();
        $local      = Extension::all()->keyBy('number');
        $statuses   = collect($remote['data'] ?? []);

        // Merge remote status with local extension data
        $agents = $statuses->map(function ($agent) use ($local) {
            $ext = $local->get($agent['number'] ?? '');
            return array_merge($agent, [
                'name'       => $ext?->name,
                'email'      => $ext?->email,
                'department' => $ext?->department,
            ]);
        });

        return response()->json($agents);
    }

    /** GET /mikopbx/agents/online */
    public function online(): JsonResponse
    {
        return response()->json(MikoPBX::agent()->getOnlineAgents());
    }

    /** GET /mikopbx/agents/{extension}/status */
    public function status(string $extension): JsonResponse
    {
        return response()->json([
            'extension' => $extension,
            'status'    => MikoPBX::agent()->status($extension),
            'calls'     => MikoPBX::agent()->getActiveCalls($extension),
        ]);
    }

    /** POST /mikopbx/agents/{extension}/call */
    public function call(Request $request, string $extension): JsonResponse
    {
        $r = $request->validate(['number' => 'required|string']);
        return response()->json(MikoPBX::agent()->callCustomer($extension, $r['number']));
    }

    /** GET /mikopbx/agents/queue-status */
    public function queueStatus(Request $request): JsonResponse
    {
        return response()->json(MikoPBX::ami()->queueStatus($request->queue));
    }

    /** POST /mikopbx/agents/{extension}/queue/pause */
    public function pauseInQueue(Request $request, string $extension): JsonResponse
    {
        $r = $request->validate(['queue' => 'required|string', 'reason' => 'nullable|string']);
        return response()->json(MikoPBX::ami()->queuePause($r['queue'], "PJSIP/{$extension}", $r['reason'] ?? ''));
    }

    /** POST /mikopbx/agents/{extension}/queue/unpause */
    public function unpauseInQueue(Request $request, string $extension): JsonResponse
    {
        $r = $request->validate(['queue' => 'required|string']);
        return response()->json(MikoPBX::ami()->queueUnpause($r['queue'], "PJSIP/{$extension}"));
    }
}

// ═══════════════════════════════════════════════════════════════════
// RECORDING CONTROLLER
// ═══════════════════════════════════════════════════════════════════

class RecordingController
{
    public function __construct(private RecordingService $recordings) {}

    /** GET /mikopbx/recordings */
    public function index(Request $request): JsonResponse
    {
        $from = $request->date_from ?? now()->startOfMonth()->toDateString();
        $to   = $request->date_to   ?? now()->toDateString();

        return response()->json($this->recordings->getAll($from, $to, $request->extension));
    }

    /** GET /mikopbx/recordings/today */
    public function today(Request $request): JsonResponse
    {
        return response()->json($this->recordings->getToday($request->extension));
    }

    /** GET /mikopbx/recordings/{filename}/download */
    public function download(string $filename): \Illuminate\Http\Response
    {
        $url      = $this->recordings->getDownloadUrl($filename);
        $contents = file_get_contents($url);

        return response($contents, 200, [
            'Content-Type'        => 'audio/wav',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /** GET /mikopbx/recordings/stats */
    public function stats(Request $request): JsonResponse
    {
        $from = $request->date_from ?? now()->startOfMonth()->toDateString();
        $to   = $request->date_to   ?? now()->toDateString();

        return response()->json($this->recordings->getStats($from, $to));
    }
}

// ═══════════════════════════════════════════════════════════════════
// CONFERENCE CONTROLLER
// ═══════════════════════════════════════════════════════════════════

class ConferenceController
{
    public function __construct(private ConferenceService $conferences) {}

    /** GET /mikopbx/conferences */
    public function index(): JsonResponse
    {
        return response()->json($this->conferences->getAll());
    }

    /** POST /mikopbx/conferences */
    public function create(Request $request): JsonResponse
    {
        $r = $request->validate(['name' => 'nullable|string']);
        return response()->json($this->conferences->create($r['name'] ?? ''), 201);
    }

    /** POST /mikopbx/conferences/{id}/participants */
    public function addParticipant(Request $request, string $bridgeId): JsonResponse
    {
        $r = $request->validate([
            'channel'  => 'nullable|string',
            'number'   => 'nullable|string',
        ]);

        if (!empty($r['number'])) {
            return response()->json($this->conferences->dialIn($bridgeId, "PJSIP/{$r['number']}"));
        }

        return response()->json($this->conferences->addParticipant($bridgeId, $r['channel']));
    }

    /** DELETE /mikopbx/conferences/{id}/participants/{channel} */
    public function removeParticipant(string $bridgeId, string $channelId): JsonResponse
    {
        return response()->json($this->conferences->removeParticipant($bridgeId, $channelId));
    }

    /** POST /mikopbx/conferences/{id}/mute/{channel} */
    public function muteParticipant(string $bridgeId, string $channelId): JsonResponse
    {
        return response()->json($this->conferences->muteParticipant($channelId));
    }

    /** POST /mikopbx/conferences/{id}/record */
    public function startRecording(string $bridgeId): JsonResponse
    {
        return response()->json($this->conferences->startRecording($bridgeId));
    }

    /** DELETE /mikopbx/conferences/{id} */
    public function end(string $bridgeId): JsonResponse
    {
        return response()->json($this->conferences->end($bridgeId));
    }
}

// ═══════════════════════════════════════════════════════════════════
// IVR CONTROLLER
// ═══════════════════════════════════════════════════════════════════

class IVRController
{
    /** POST /mikopbx/ivr/build */
    public function build(Request $request): JsonResponse
    {
        $r = $request->validate([
            'name'       => 'required|string',
            'greeting'   => 'required|string',
            'timeout'    => 'integer|min:5|max:60',
            'keypresses' => 'required|array',
        ]);

        $builder = IVRBuilder::make($r['name'])
            ->greeting($r['greeting'])
            ->timeout($r['timeout'] ?? 10);

        foreach ($r['keypresses'] as $key => $action) {
            $builder->onPress($key, $action['action'], $action['value'] ?? '');
        }

        $ivr = $builder->build();

        // Optionally push to MikoPBX
        if ($request->boolean('push_to_mikopbx')) {
            MikoPBX::call()->createPolling($ivr);
        }

        return response()->json($ivr);
    }

    /** GET /mikopbx/ivr/templates */
    public function templates(): JsonResponse
    {
        return response()->json([
            'sales_support' => IVRBuilder::salesSupportTemplate('101', '102', '104'),
            'survey'        => IVRBuilder::surveyTemplate('103'),
        ]);
    }
}

// ═══════════════════════════════════════════════════════════════════
// SYSTEM CONTROLLER
// ═══════════════════════════════════════════════════════════════════

class SystemController
{
    /** GET /mikopbx/system/status */
    public function status(): JsonResponse
    {
        return response()->json([
            'version'      => MikoPBX::call()->getVersion(),
            'ami_ping'     => MikoPBX::ami()->ping(),
            'active_calls' => count(MikoPBX::call()->getActiveCalls()['data'] ?? []),
            'uptime'       => MikoPBX::ami()->getUptime(),
        ]);
    }

    /** POST /mikopbx/system/reload */
    public function reload(): JsonResponse
    {
        return response()->json(MikoPBX::ami()->reloadDialplan());
    }

    /** GET /mikopbx/system/peers */
    public function peers(): JsonResponse
    {
        return response()->json(MikoPBX::ami()->getPJSIPEndpoints());
    }

    /** POST /mikopbx/system/command */
    public function command(Request $request): JsonResponse
    {
        $r = $request->validate(['command' => 'required|string']);
        return response()->json(MikoPBX::ami()->command($r['command']));
    }
}
