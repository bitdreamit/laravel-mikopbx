<?php

namespace BitDreamIT\MikoPBX\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use BitDreamIT\MikoPBX\Services\ConferenceService;

class ConferenceController extends Controller
{
    public function __construct(private ConferenceService $svc) {}

    public function index()
    {
        try {
            $rooms = $this->svc->getRooms()['data'] ?? [];
        } catch (\Throwable) {
            $rooms = [];
        }
        return view('mikopbx::conference.index', compact('rooms'));
    }

    public function kick(Request $request)
    {
        $d = $request->validate(['channel' => 'required', 'room' => 'required']);
        $this->svc->kickParticipant($d['channel'], $d['room']);
        return response()->json(['success' => true]);
    }

    public function mute(Request $request)
    {
        $d = $request->validate(['channel' => 'required', 'room' => 'required']);
        $this->svc->muteParticipant($d['channel'], $d['room']);
        return response()->json(['success' => true]);
    }
}
