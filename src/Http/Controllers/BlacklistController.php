<?php

namespace BitDreamIT\MikoPBX\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use BitDreamIT\MikoPBX\Services\BlacklistService;

class BlacklistController extends Controller
{
    public function __construct(private BlacklistService $svc) {}

    public function index()
    {
        $list = $this->svc->all();
        return view('mikopbx::blacklist.index', compact('list'));
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'number'    => 'required|string|max:30',
            'reason'    => 'nullable|string|max:200',
            'direction' => 'in:inbound,outbound,both',
            'expires_at'=> 'nullable|date',
        ]);
        $this->svc->add($d['number'], $d['reason'] ?? '', $d['direction'] ?? 'both', $d['expires_at'] ?? null);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }
        return back()->with('success', "Number {$d['number']} added to blacklist.");
    }

    public function destroy(string $number)
    {
        $this->svc->remove($number);
        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }
        return back()->with('success', "Number {$number} removed from blacklist.");
    }

    public function check(Request $request)
    {
        $number = $request->validate(['number' => 'required|string'])['number'];
        return response()->json([
            'number'   => $number,
            'blocked'  => $this->svc->isBlocked($number),
        ]);
    }
}
