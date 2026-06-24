<?php

namespace BitDreamIT\MikoPBX\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use BitDreamIT\MikoPBX\Services\AnalyticsService;

class AnalyticsController extends Controller
{
    public function __construct(private AnalyticsService $svc) {}

    public function index(Request $request)
    {
        $from = $request->from ?? now()->subDays(30)->format('Y-m-d');
        $to   = $request->to   ?? now()->format('Y-m-d');

        $summary   = $this->svc->summary($from, $to);
        $daily     = $this->svc->dailyTrend($from, $to);
        $peakHours = $this->svc->peakHours($from, $to);
        $agents    = $this->svc->agentPerformance($from, $to);
        $byStatus  = $this->svc->callsByStatus($from, $to);

        return view('mikopbx::analytics.index', compact(
            'summary','daily','peakHours','agents','byStatus','from','to'
        ));
    }

    public function api(Request $request)
    {
        $from = $request->from ?? now()->subDays(30)->format('Y-m-d');
        $to   = $request->to   ?? now()->format('Y-m-d');

        return response()->json([
            'summary'    => $this->svc->summary($from, $to),
            'daily'      => $this->svc->dailyTrend($from, $to),
            'peak_hours' => $this->svc->peakHours($from, $to),
            'agents'     => $this->svc->agentPerformance($from, $to),
            'by_status'  => $this->svc->callsByStatus($from, $to),
        ]);
    }
}
