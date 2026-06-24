<?php

namespace BitDreamIT\MikoPBX\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use BitDreamIT\MikoPBX\Models\{CallLog, Extension, Campaign, Callback, Blacklist};
use BitDreamIT\MikoPBX\Services\{RestApiService, AgentService, AnalyticsService};

class DashboardController extends Controller
{
    public function __construct(
        private RestApiService $api,
        private AgentService $agents,
        private AnalyticsService $analytics,
    ) {}

    public function index()
    {
        $today    = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $stats = [
            'total_calls'     => CallLog::whereDate('started_at', today())->count(),
            'answered'        => CallLog::whereDate('started_at', today())->where('status','answered')->count(),
            'missed'          => CallLog::whereDate('started_at', today())->where('status','missed')->count(),
            'agents_online'   => Extension::whereIn('status',['online','busy'])->count(),
            'agents_total'    => Extension::where('active', true)->count(),
            'active_calls'    => 0,
            'pending_callbacks'=> Callback::where('status','pending')->count(),
            'running_campaigns'=> Campaign::where('status','running')->count(),
        ];

        try {
            $active = $this->api->getActiveCalls();
            $stats['active_calls'] = count($active['data'] ?? []);
        } catch (\Throwable) {}

        $recentCalls = CallLog::with([])
            ->latest('started_at')
            ->limit(10)
            ->get();

        $pendingCallbacks = Callback::where('status','pending')
            ->orderBy('priority','desc')
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get();

        return view('mikopbx::dashboard.index', compact(
            'stats', 'recentCalls', 'pendingCallbacks'
        ));
    }
}
