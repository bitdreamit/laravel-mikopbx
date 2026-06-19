<?php

namespace BitDreamIT\MikoPBX\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use BitDreamIT\MikoPBX\Facades\MikoPBX;
use BitDreamIT\MikoPBX\Http\Requests\AnalyticsRequest;
use BitDreamIT\MikoPBX\Http\Requests\BlacklistRequest;
use BitDreamIT\MikoPBX\Http\Resources\CallLogResource;
use BitDreamIT\MikoPBX\Http\Resources\CampaignResource;
use BitDreamIT\MikoPBX\Http\Resources\AgentResource;
use BitDreamIT\MikoPBX\Http\Resources\AnalyticsDashboardResource;
use BitDreamIT\MikoPBX\Models\CallLog;
use BitDreamIT\MikoPBX\Models\Blacklist;
use BitDreamIT\MikoPBX\Models\CallbackRequest;

// ═══════════════════════════════════════════════════════════
// ANALYTICS CONTROLLER
// ═══════════════════════════════════════════════════════════

class AnalyticsController
{
    /** GET /mikopbx/analytics/dashboard */
    public function dashboard(AnalyticsRequest $request): JsonResponse
    {
        $from = $request->date_from ?? today()->toDateString();
        $to   = $request->date_to   ?? today()->toDateString();
        return response()->json(new AnalyticsDashboardResource(
            MikoPBX::analytics()->dashboard($from, $to)
        ));
    }

    /** GET /mikopbx/analytics/peak-hours */
    public function peakHours(AnalyticsRequest $request): JsonResponse
    {
        $from = $request->date_from ?? today()->subDays(7)->toDateString();
        $to   = $request->date_to   ?? today()->toDateString();
        return response()->json(['data' => MikoPBX::analytics()->peakHours($from, $to)]);
    }

    /** GET /mikopbx/analytics/daily-trend */
    public function dailyTrend(AnalyticsRequest $request): JsonResponse
    {
        $from = $request->date_from ?? today()->subDays(30)->toDateString();
        $to   = $request->date_to   ?? today()->toDateString();
        return response()->json(['data' => MikoPBX::analytics()->dailyTrend($from, $to)]);
    }

    /** GET /mikopbx/analytics/agent-performance */
    public function agentPerformance(AnalyticsRequest $request): JsonResponse
    {
        $from = $request->date_from ?? today()->startOfMonth()->toDateString();
        $to   = $request->date_to   ?? today()->toDateString();
        return response()->json(['data' => MikoPBX::analytics()->agentPerformance($from, $to)]);
    }

    /** GET /mikopbx/analytics/sla */
    public function sla(AnalyticsRequest $request): JsonResponse
    {
        $from = $request->date_from ?? today()->toDateString();
        $to   = $request->date_to   ?? today()->toDateString();
        return response()->json(MikoPBX::analytics()->slaCompliance($from, $to, $request->sla_seconds ?? 20));
    }

    /** GET /mikopbx/analytics/abandoned */
    public function abandoned(AnalyticsRequest $request): JsonResponse
    {
        $from = $request->date_from ?? today()->toDateString();
        $to   = $request->date_to   ?? today()->toDateString();
        return response()->json(MikoPBX::analytics()->abandonedCalls($from, $to));
    }

    /** GET /mikopbx/analytics/top-callers */
    public function topCallers(AnalyticsRequest $request): JsonResponse
    {
        $from = $request->date_from ?? today()->startOfMonth()->toDateString();
        $to   = $request->date_to   ?? today()->toDateString();
        return response()->json(['data' => MikoPBX::analytics()->topCallers($from, $to, $request->limit ?? 10)]);
    }

    /** GET /mikopbx/analytics/hangup-causes */
    public function hangupCauses(AnalyticsRequest $request): JsonResponse
    {
        $from = $request->date_from ?? today()->toDateString();
        $to   = $request->date_to   ?? today()->toDateString();
        return response()->json(['data' => MikoPBX::analytics()->hangupCauses($from, $to)]);
    }

    /** GET /mikopbx/analytics/weekly-comparison */
    public function weeklyComparison(): JsonResponse
    {
        return response()->json(MikoPBX::analytics()->weeklyComparison());
    }

    /** GET /mikopbx/analytics/export */
    public function export(AnalyticsRequest $request): \Illuminate\Http\Response
    {
        $from = $request->date_from ?? today()->startOfMonth()->toDateString();
        $to   = $request->date_to   ?? today()->toDateString();
        $csv  = MikoPBX::analytics()->exportCsv($from, $to);
        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"cdr-{$from}-to-{$to}.csv\"",
        ]);
    }
}

// ═══════════════════════════════════════════════════════════
// BLACKLIST CONTROLLER
// ═══════════════════════════════════════════════════════════

class BlacklistController
{
    /** GET /mikopbx/blacklist */
    public function index(): JsonResponse
    {
        return response()->json(['data' => MikoPBX::blacklist()->getAll()]);
    }

    /** POST /mikopbx/blacklist */
    public function store(BlacklistRequest $request): JsonResponse
    {
        $entry = MikoPBX::blacklist()->block(
            $request->number,
            $request->reason    ?? '',
            $request->expires_at ?? null
        );
        return response()->json($entry, 201);
    }

    /** DELETE /mikopbx/blacklist/{number} */
    public function destroy(string $number): JsonResponse
    {
        $deleted = MikoPBX::blacklist()->unblock($number);
        return response()->json(['deleted' => $deleted]);
    }

    /** GET /mikopbx/blacklist/check/{number} */
    public function check(string $number): JsonResponse
    {
        return response()->json([
            'number'  => $number,
            'blocked' => MikoPBX::blacklist()->isBlocked($number),
        ]);
    }

    /** POST /mikopbx/blacklist/clean-expired */
    public function cleanExpired(): JsonResponse
    {
        $count = MikoPBX::blacklist()->cleanExpired();
        return response()->json(['cleaned' => $count]);
    }
}

// ═══════════════════════════════════════════════════════════
// CALLBACK CONTROLLER
// ═══════════════════════════════════════════════════════════

class CallbackController
{
    /** GET /mikopbx/callbacks */
    public function index(Request $request): JsonResponse
    {
        $callbacks = CallbackRequest::query()
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(20);
        return response()->json($callbacks);
    }

    /** POST /mikopbx/callbacks */
    public function store(Request $request): JsonResponse
    {
        $r = $request->validate([
            'caller_number' => 'required|string',
            'extension'     => 'nullable|string',
            'delay_minutes' => 'nullable|integer|min:0|max:1440',
        ]);

        $callback = MikoPBX::callback()->schedule(
            $r['caller_number'],
            $r['extension']     ?? '',
            $r['delay_minutes'] ?? 5
        );
        return response()->json($callback, 201);
    }

    /** DELETE /mikopbx/callbacks/{id} */
    public function cancel(int $id): JsonResponse
    {
        return response()->json(['cancelled' => MikoPBX::callback()->cancel($id)]);
    }

    /** GET /mikopbx/callbacks/pending */
    public function pending(): JsonResponse
    {
        return response()->json(['data' => MikoPBX::callback()->getPending()]);
    }
}

// ═══════════════════════════════════════════════════════════
// HEALTH CONTROLLER
// ═══════════════════════════════════════════════════════════

class HealthController
{
    /** GET /mikopbx/health */
    public function check(): JsonResponse
    {
        $health = MikoPBX::health()->check();
        $code   = $health['overall'] === 'unhealthy' ? 503 : 200;
        return response()->json($health, $code);
    }

    /** GET /mikopbx/health/ping */
    public function ping(): JsonResponse
    {
        return response()->json([
            'ok'        => MikoPBX::health()->ping(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /** GET /mikopbx/health/system */
    public function system(): JsonResponse
    {
        return response()->json(MikoPBX::health()->systemInfo());
    }
}
