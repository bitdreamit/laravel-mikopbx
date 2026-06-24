<?php

namespace BitDreamIT\MikoPBX\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use BitDreamIT\MikoPBX\Services\IVRService;

class IVRController extends Controller
{
    public function __construct(private IVRService $svc) {}

    public function index()
    {
        try {
            $menus = $this->svc->getMenus()['data'] ?? [];
        } catch (\Throwable) {
            $menus = [];
        }
        return view('mikopbx::ivr.index', compact('menus'));
    }

    public function builder()
    {
        return view('mikopbx::ivr.builder');
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'nodes'    => 'required|array',
            'greeting' => 'nullable|string',
            'timeout'  => 'integer|min:1|max:30',
        ]);

        try {
            $result = $this->svc->save($data);
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
