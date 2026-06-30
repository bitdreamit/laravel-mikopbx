<?php

namespace BitDreamIT\MikoPBX\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use BitDreamIT\MikoPBX\Models\Extension;
use BitDreamIT\MikoPBX\Services\AgentService;
use BitDreamIT\MikoPBX\Events\AgentStatusChangedEvent;

class AgentController extends Controller
{
    public function __construct(private AgentService $svc) {}

    public function index()
    {
        $agents = $this->svc->all();
        return view('mikopbx::agents.index', compact('agents'));
    }

    public function statuses()
    {
        return response()->json($this->svc->all()->map(fn($a) => [
            'extension' => $a->extension,
            'name'      => $a->name,
            'status'    => $a->status,
            'color'     => $a->status_color,
            'dot'       => $a->status_dot,
        ]));
    }

    /**
     * Manual status change (e.g. agent sets DND from the Agents page dropdown).
     */
    public function setStatus(Request $request)
    {
        $request->validate([
            'extension' => 'required|string',
            'status'    => 'required|in:online,offline,busy,dnd,away',
        ]);

        $this->svc->setStatus($request->extension, $request->status);
        $this->broadcastStatus($request->extension);

        return response()->json(['success' => true]);
    }

    /**
     * Called by the browser's JsSIP UA when it registers/unregisters
     * or enters/leaves an active call via the WebRTC web dialer.
     *
     * This is what makes the agent show "online" / "busy" in the
     * AgentStatusGrid even though MikoPBX's SIP peer state for a
     * WebRTC (-WS) registration doesn't always reflect cleanly
     * through sip:getPeersStatuses.
     *
     * status values: online | offline | busy
     *   online  → JsSIP UA registered, idle
     *   busy    → JsSIP UA registered, in an active call
     *   offline → JsSIP UA disconnected / unregistered
     */
    public function webDialerStatus(Request $request)
    {
        $request->validate([
            'extension' => 'required|string',
            'status'    => 'required|in:online,offline,busy',
        ]);

        $extension = $request->extension;
        $status    = $request->status;

        // Only allow a user to report status for their OWN extension
        $myExtension = auth()->user()?->pbx_extension;
        if ($myExtension && $myExtension !== $extension) {
            return response()->json(['success' => false, 'message' => 'Extension mismatch'], 403);
        }

        $ext = Extension::firstOrCreate(
            ['extension' => $extension],
            ['name' => auth()->user()?->name ?? $extension, 'active' => true]
        );

        $ext->update([
            'status'       => $status,
            'last_seen_at' => $status !== 'offline' ? now() : $ext->last_seen_at,
        ]);

        $this->broadcastStatus($extension);

        return response()->json(['success' => true, 'extension' => $extension, 'status' => $status]);
    }

    public function sync()
    {
        $count = $this->svc->sync();
        return back()->with('success', "Synced {$count} extensions from MikoPBX.");
    }

    private function broadcastStatus(string $extension): void
    {
        $agent = Extension::where('extension', $extension)->first();
        if ($agent) {
            event(new AgentStatusChangedEvent($agent, $agent->status));
        }
    }
}
