<?php

namespace BitDreamIT\MikoPBX\Services;

use BitDreamIT\MikoPBX\Models\CallLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Analytics Service
 *
 * Comprehensive call analytics, KPI tracking, and reporting.
 * Covers: SLA compliance, agent performance, peak hour analysis,
 * abandoned call rate, average handle time, first call resolution.
 */
class AnalyticsService
{
    public function __construct(private RestApiService $api) {}

    // ─────────────────────────────────────────
    // DASHBOARD KPIs
    // ─────────────────────────────────────────

    public function dashboard(string $dateFrom, string $dateTo): array
    {
        $logs = CallLog::whereBetween('started_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        $total    = (clone $logs)->count();
        $answered = (clone $logs)->where('status', 'answered')->count();
        $missed   = (clone $logs)->where('status', 'missed')->count();
        $busy     = (clone $logs)->where('status', 'busy')->count();

        $avgDuration = (clone $logs)->where('status', 'answered')->avg('duration') ?? 0;
        $avgWait     = (clone $logs)->whereNotNull('answered_at')->avg(\DB::raw('TIMESTAMPDIFF(SECOND, started_at, answered_at)')) ?? 0;

        return [
            'period'              => ['from' => $dateFrom, 'to' => $dateTo],
            'total_calls'         => $total,
            'answered_calls'      => $answered,
            'missed_calls'        => $missed,
            'busy_calls'          => $busy,
            'answer_rate'         => $total > 0 ? round(($answered / $total) * 100, 2) : 0,
            'miss_rate'           => $total > 0 ? round(($missed  / $total) * 100, 2) : 0,
            'avg_handle_time'     => round($avgDuration),
            'avg_wait_time'       => round($avgWait),
            'total_talk_time'     => (clone $logs)->sum('duration'),
            'inbound_calls'       => (clone $logs)->where('direction', 'inbound')->count(),
            'outbound_calls'      => (clone $logs)->where('direction', 'outbound')->count(),
            'internal_calls'      => (clone $logs)->where('direction', 'internal')->count(),
        ];
    }

    // ─────────────────────────────────────────
    // HOURLY ANALYSIS (Peak Hours)
    // ─────────────────────────────────────────

    public function peakHours(string $dateFrom, string $dateTo): array
    {
        return CallLog::whereBetween('started_at', [$dateFrom, $dateTo])
            ->selectRaw('HOUR(started_at) as hour, COUNT(*) as total, SUM(CASE WHEN status="answered" THEN 1 ELSE 0 END) as answered')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->map(fn($r) => [
                'hour'        => str_pad($r->hour, 2, '0', STR_PAD_LEFT) . ':00',
                'total'       => $r->total,
                'answered'    => $r->answered,
                'answer_rate' => $r->total > 0 ? round(($r->answered / $r->total) * 100) : 0,
            ])
            ->toArray();
    }

    // ─────────────────────────────────────────
    // DAILY TREND
    // ─────────────────────────────────────────

    public function dailyTrend(string $dateFrom, string $dateTo): array
    {
        return CallLog::whereBetween('started_at', [$dateFrom, $dateTo])
            ->selectRaw('DATE(started_at) as date, COUNT(*) as total, SUM(CASE WHEN status="answered" THEN 1 ELSE 0 END) as answered, SUM(CASE WHEN status="missed" THEN 1 ELSE 0 END) as missed, AVG(duration) as avg_duration')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    // ─────────────────────────────────────────
    // AGENT PERFORMANCE
    // ─────────────────────────────────────────

    public function agentPerformance(string $dateFrom, string $dateTo): array
    {
        return CallLog::whereBetween('started_at', [$dateFrom, $dateTo])
            ->whereNotNull('extension')
            ->selectRaw('
                extension,
                COUNT(*) as total_calls,
                SUM(CASE WHEN status="answered" THEN 1 ELSE 0 END) as answered,
                SUM(CASE WHEN status="missed"   THEN 1 ELSE 0 END) as missed,
                AVG(CASE WHEN status="answered" THEN duration END)  as avg_duration,
                SUM(duration) as total_talk_time,
                MAX(started_at) as last_call_at
            ')
            ->groupBy('extension')
            ->orderByDesc('total_calls')
            ->get()
            ->map(fn($r) => [
                'extension'       => $r->extension,
                'total_calls'     => $r->total_calls,
                'answered'        => $r->answered,
                'missed'          => $r->missed,
                'answer_rate'     => $r->total_calls > 0 ? round(($r->answered / $r->total_calls) * 100, 1) : 0,
                'avg_duration'    => round($r->avg_duration ?? 0),
                'total_talk_time' => $r->total_talk_time,
                'last_call_at'    => $r->last_call_at,
            ])
            ->toArray();
    }

    // ─────────────────────────────────────────
    // SLA COMPLIANCE
    // ─────────────────────────────────────────

    /** SLA: calls answered within N seconds */
    public function slaCompliance(string $dateFrom, string $dateTo, int $slaSeconds = 20): array
    {
        $total    = CallLog::whereBetween('started_at', [$dateFrom, $dateTo])->where('direction', 'inbound')->count();
        $withinSla = CallLog::whereBetween('started_at', [$dateFrom, $dateTo])
            ->where('direction', 'inbound')
            ->where('status', 'answered')
            ->whereRaw("TIMESTAMPDIFF(SECOND, started_at, answered_at) <= ?", [$slaSeconds])
            ->count();

        return [
            'sla_target_seconds' => $slaSeconds,
            'total_inbound'      => $total,
            'within_sla'         => $withinSla,
            'outside_sla'        => $total - $withinSla,
            'sla_percentage'     => $total > 0 ? round(($withinSla / $total) * 100, 2) : 0,
        ];
    }

    // ─────────────────────────────────────────
    // ABANDONED CALLS
    // ─────────────────────────────────────────

    public function abandonedCalls(string $dateFrom, string $dateTo): array
    {
        $abandoned = CallLog::whereBetween('started_at', [$dateFrom, $dateTo])
            ->where('direction', 'inbound')
            ->where('status', 'missed')
            ->selectRaw('caller, COUNT(*) as attempts, MAX(started_at) as last_attempt')
            ->groupBy('caller')
            ->orderByDesc('attempts')
            ->get();

        return [
            'total_abandoned'   => $abandoned->sum('attempts'),
            'unique_callers'    => $abandoned->count(),
            'callers'           => $abandoned->toArray(),
        ];
    }

    // ─────────────────────────────────────────
    // CALLER FREQUENCY
    // ─────────────────────────────────────────

    public function topCallers(string $dateFrom, string $dateTo, int $limit = 10): array
    {
        return CallLog::whereBetween('started_at', [$dateFrom, $dateTo])
            ->where('direction', 'inbound')
            ->selectRaw('caller, caller_name, COUNT(*) as total, SUM(duration) as total_duration')
            ->groupBy('caller', 'caller_name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    // ─────────────────────────────────────────
    // CALL CAUSE BREAKDOWN
    // ─────────────────────────────────────────

    public function hangupCauses(string $dateFrom, string $dateTo): array
    {
        return CallLog::whereBetween('started_at', [$dateFrom, $dateTo])
            ->whereNotNull('cause')
            ->selectRaw('cause, COUNT(*) as total')
            ->groupBy('cause')
            ->orderByDesc('total')
            ->get()
            ->toArray();
    }

    // ─────────────────────────────────────────
    // WEEKLY COMPARISON
    // ─────────────────────────────────────────

    public function weeklyComparison(): array
    {
        $thisWeek = CallLog::whereBetween('started_at', [now()->startOfWeek(), now()->endOfWeek()]);
        $lastWeek = CallLog::whereBetween('started_at', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()]);

        return [
            'this_week' => [
                'total'    => (clone $thisWeek)->count(),
                'answered' => (clone $thisWeek)->where('status', 'answered')->count(),
                'missed'   => (clone $thisWeek)->where('status', 'missed')->count(),
            ],
            'last_week' => [
                'total'    => (clone $lastWeek)->count(),
                'answered' => (clone $lastWeek)->where('status', 'answered')->count(),
                'missed'   => (clone $lastWeek)->where('status', 'missed')->count(),
            ],
        ];
    }

    // ─────────────────────────────────────────
    // CDR EXPORT
    // ─────────────────────────────────────────

    public function exportCsv(string $dateFrom, string $dateTo): string
    {
        $logs = CallLog::whereBetween('started_at', [$dateFrom, $dateTo])->get();

        $csv  = "ID,Caller,Extension,Direction,Status,Duration,Cause,Started At,Ended At\n";
        foreach ($logs as $log) {
            $csv .= implode(',', [
                $log->id, $log->caller, $log->extension,
                $log->direction, $log->status, $log->duration,
                $log->cause, $log->started_at, $log->ended_at,
            ]) . "\n";
        }

        return $csv;
    }
}
