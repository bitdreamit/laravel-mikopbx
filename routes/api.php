<?php

use Illuminate\Support\Facades\Route;
use BitDreamIT\MikoPBX\Http\Controllers\{CallController,CampaignController,AgentController,RecordingController,ConferenceController,IVRController,SystemController};
use BitDreamIT\MikoPBX\Http\Controllers\{AnalyticsController,BlacklistController,CallbackController,HealthController};
use BitDreamIT\MikoPBX\Webhooks\WebhookController;

// ── Calls ──────────────────────────────────────────────────
Route::prefix('calls')->group(function () {
    Route::get('active',              [CallController::class, 'active']);
    Route::get('logs',                [CallController::class, 'logs']);
    Route::get('stats',               [CallController::class, 'stats']);
    Route::get('parked',              [CallController::class, 'parked']);
    Route::post('originate',          [CallController::class, 'originate']);
    Route::post('transfer',           [CallController::class, 'transfer']);
    Route::post('hangup',             [CallController::class, 'hangup']);
    Route::post('hold',               [CallController::class, 'hold']);
    Route::post('mute',               [CallController::class, 'mute']);
    Route::post('unmute',             [CallController::class, 'unmute']);
    Route::post('park',               [CallController::class, 'park']);
});

// ── Campaigns ─────────────────────────────────────────────
Route::prefix('campaigns')->group(function () {
    Route::get('/',                   [CampaignController::class, 'index']);
    Route::post('/',                  [CampaignController::class, 'store']);
    Route::get('{id}',                [CampaignController::class, 'show']);
    Route::delete('{id}',             [CampaignController::class, 'destroy']);
    Route::post('{id}/start',         [CampaignController::class, 'start']);
    Route::post('{id}/stop',          [CampaignController::class, 'stop']);
    Route::get('{id}/status',         [CampaignController::class, 'status']);
});

// ── Agents ────────────────────────────────────────────────
Route::prefix('agents')->group(function () {
    Route::get('/',                   [AgentController::class, 'index']);
    Route::get('online',              [AgentController::class, 'online']);
    Route::get('queue-status',        [AgentController::class, 'queueStatus']);
    Route::get('{ext}/status',        [AgentController::class, 'status']);
    Route::post('{ext}/call',         [AgentController::class, 'call']);
    Route::post('{ext}/queue/pause',  [AgentController::class, 'pauseInQueue']);
    Route::post('{ext}/queue/unpause',[AgentController::class, 'unpauseInQueue']);
});

// ── Recordings ────────────────────────────────────────────
Route::prefix('recordings')->group(function () {
    Route::get('/',                   [RecordingController::class, 'index']);
    Route::get('today',               [RecordingController::class, 'today']);
    Route::get('stats',               [RecordingController::class, 'stats']);
    Route::get('{filename}/download', [RecordingController::class, 'download'])->where('filename', '.*');
});

// ── Conferences ───────────────────────────────────────────
Route::prefix('conferences')->group(function () {
    Route::get('/',                   [ConferenceController::class, 'index']);
    Route::post('/',                  [ConferenceController::class, 'create']);
    Route::delete('{id}',             [ConferenceController::class, 'end']);
    Route::post('{id}/participants',            [ConferenceController::class, 'addParticipant']);
    Route::delete('{id}/participants/{ch}',     [ConferenceController::class, 'removeParticipant']);
    Route::post('{id}/mute/{channel}',          [ConferenceController::class, 'muteParticipant']);
    Route::post('{id}/record',                  [ConferenceController::class, 'startRecording']);
});

// ── IVR ───────────────────────────────────────────────────
Route::prefix('ivr')->group(function () {
    Route::post('build',              [IVRController::class, 'build']);
    Route::get('templates',           [IVRController::class, 'templates']);
});

// ── Analytics ─────────────────────────────────────────────
Route::prefix('analytics')->group(function () {
    Route::get('dashboard',           [AnalyticsController::class, 'dashboard']);
    Route::get('peak-hours',          [AnalyticsController::class, 'peakHours']);
    Route::get('daily-trend',         [AnalyticsController::class, 'dailyTrend']);
    Route::get('agent-performance',   [AnalyticsController::class, 'agentPerformance']);
    Route::get('sla',                 [AnalyticsController::class, 'sla']);
    Route::get('abandoned',           [AnalyticsController::class, 'abandoned']);
    Route::get('top-callers',         [AnalyticsController::class, 'topCallers']);
    Route::get('hangup-causes',       [AnalyticsController::class, 'hangupCauses']);
    Route::get('weekly-comparison',   [AnalyticsController::class, 'weeklyComparison']);
    Route::get('export',              [AnalyticsController::class, 'export']);
});

// ── Blacklist ─────────────────────────────────────────────
Route::prefix('blacklist')->group(function () {
    Route::get('/',                   [BlacklistController::class, 'index']);
    Route::post('/',                  [BlacklistController::class, 'store']);
    Route::delete('{number}',         [BlacklistController::class, 'destroy']);
    Route::get('check/{number}',      [BlacklistController::class, 'check']);
    Route::post('clean-expired',      [BlacklistController::class, 'cleanExpired']);
});

// ── Callbacks ─────────────────────────────────────────────
Route::prefix('callbacks')->group(function () {
    Route::get('/',                   [CallbackController::class, 'index']);
    Route::post('/',                  [CallbackController::class, 'store']);
    Route::get('pending',             [CallbackController::class, 'pending']);
    Route::delete('{id}',             [CallbackController::class, 'cancel']);
});

// ── System ────────────────────────────────────────────────
Route::prefix('system')->group(function () {
    Route::get('status',              [SystemController::class, 'status']);
    Route::get('peers',               [SystemController::class, 'peers']);
    Route::post('reload',             [SystemController::class, 'reload']);
    Route::post('command',            [SystemController::class, 'command']);
});

// ── Health ────────────────────────────────────────────────
Route::prefix('health')->group(function () {
    Route::get('/',                   [HealthController::class, 'check']);
    Route::get('ping',                [HealthController::class, 'ping']);
    Route::get('system',              [HealthController::class, 'system']);
});

// ── Webhook ───────────────────────────────────────────────
Route::post('webhook',                [WebhookController::class, 'handle'])->withoutMiddleware(['api']);
