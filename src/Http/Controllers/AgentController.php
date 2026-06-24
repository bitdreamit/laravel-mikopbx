<?php

namespace BitDreamIT\MikoPBX\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use BitDreamIT\MikoPBX\Models\Extension;
use BitDreamIT\MikoPBX\Services\AgentService;

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

    public function setStatus(Request $request)
    {
        $request->validate(['extension' => 'required', 'status' => 'required|in:online,offline,busy,dnd,away']);
        $this->svc->setStatus($request->extension, $request->status);
        return response()->json(['success' => true]);
    }

    public function sync()
    {
        $count = $this->svc->sync();
        return back()->with('success', "Synced {$count} extensions from MikoPBX.");
    }
}
