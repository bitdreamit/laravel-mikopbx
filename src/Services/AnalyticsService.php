<?php

namespace BitDreamIT\MikoPBX\Services;

use BitDreamIT\MikoPBX\Models\CallLog;
use BitDreamIT\MikoPBX\Models\Extension;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function summary(string $from, string $to): array
    {
        $p = config('mikopbx.table_prefix', 'mikopbx_');
        $q = CallLog::whereBetween('started_at', [$from, $to]);

        $total    = (clone $q)->count();
        $answered = (clone $q)->where('status', 'answered')->count();
        $missed   = (clone $q)->where('status', 'missed')->count();
        $avgDur   = (clone $q)->where('status', 'answered')->avg('billsec') ?? 0;

        return [
            'total_calls'   => $total,
            'answered'      => $answered,
            'missed'        => $missed,
            'failed'        => (clone $q)->whereIn('status', ['busy', 'failed'])->count(),
            'asr'           => $total > 0 ? round($answered / $total * 100, 1) : 0,
            'avg_duration'  => round($avgDur),
            'inbound'       => (clone $q)->where('direction', 'inbound')->count(),
            'outbound'      => (clone $q)->where('direction', 'outbound')->count(),
            'internal'      => (clone $q)->where('direction', 'internal')->count(),
        ];
    }

    public function dailyTrend(string $from, string $to): array
    {
        return CallLog::whereBetween('started_at', [$from, $to])
            ->selectRaw('DATE(started_at) as date, COUNT(*) as total,
                SUM(status = "answered") as answered,
                SUM(status = "missed") as missed')
            ->groupByRaw('DATE(started_at)')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    public function peakHours(string $from, string $to): array
    {
        return CallLog::whereBetween('started_at', [$from, $to])
            ->selectRaw('HOUR(started_at) as hour, COUNT(*) as calls')
            ->groupByRaw('HOUR(started_at)')
            ->orderBy('hour')
            ->pluck('calls', 'hour')
            ->toArray();
    }

    public function agentPerformance(string $from, string $to): array
    {
        return CallLog::whereBetween('started_at', [$from, $to])
            ->whereNotNull('extension')
            ->selectRaw('extension,
                COUNT(*) as total,
                SUM(status = "answered") as answered,
                ROUND(AVG(billsec)) as avg_duration,
                MAX(billsec) as longest')
            ->groupBy('extension')
            ->orderByDesc('total')
            ->get()
            ->toArray();
    }

    public function callsByStatus(string $from, string $to): array
    {
        return CallLog::whereBetween('started_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }
}
