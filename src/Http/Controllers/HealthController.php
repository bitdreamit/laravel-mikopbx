<?php

namespace BitDreamIT\MikoPBX\Http\Controllers;

use Illuminate\Routing\Controller;
use BitDreamIT\MikoPBX\Services\HealthCheckService;

class HealthController extends Controller
{
    public function __construct(private HealthCheckService $svc) {}

    public function index()
    {
        $latest  = $this->svc->latest();
        $history = $this->svc->history(24);
        return view('mikopbx::health.index', compact('latest','history'));
    }

    public function check()
    {
        $result = $this->svc->check();
        return response()->json($result);
    }
}
