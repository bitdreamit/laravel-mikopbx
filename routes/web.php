<?php

use Illuminate\Support\Facades\Route;
use BitDreamIT\MikoPBX\Http\Controllers\{
    DashboardController,
    CallController,
    CampaignController,
    AgentController,
    AnalyticsController,
    RecordingController,
    BlacklistController,
    CallbackController,
    ConferenceController,
    IVRController,
    HealthController,
    WebDialerController,
};

// Dashboard
Route::get('/',           [DashboardController::class, 'index'])->name('dashboard');

// Calls
Route::get('/calls',                  [CallController::class, 'index'])->name('calls.index');
Route::get('/calls/{call}',           [CallController::class, 'show'])->name('calls.show');
Route::get('/calls/active/json',      [CallController::class, 'active'])->name('calls.active');
Route::post('/calls/originate',       [CallController::class, 'originate'])->name('calls.originate');
Route::post('/calls/transfer',        [CallController::class, 'transfer'])->name('calls.transfer');
Route::post('/calls/hangup',          [CallController::class, 'hangup'])->name('calls.hangup');
Route::post('/calls/mute',            [CallController::class, 'mute'])->name('calls.mute');

// Campaigns
Route::get('/campaigns',              [CampaignController::class, 'index'])->name('campaigns.index');
Route::get('/campaigns/create',       [CampaignController::class, 'create'])->name('campaigns.create');
Route::post('/campaigns',             [CampaignController::class, 'store'])->name('campaigns.store');
Route::get('/campaigns/{campaign}',   [CampaignController::class, 'show'])->name('campaigns.show');
Route::post('/campaigns/{campaign}/start',    [CampaignController::class, 'start'])->name('campaigns.start');
Route::post('/campaigns/{campaign}/pause',    [CampaignController::class, 'pause'])->name('campaigns.pause');
Route::post('/campaigns/{campaign}/stop',     [CampaignController::class, 'stop'])->name('campaigns.stop');
Route::get('/campaigns/{campaign}/progress',  [CampaignController::class, 'progress'])->name('campaigns.progress');

// Agents
Route::get('/agents',                 [AgentController::class, 'index'])->name('agents.index');
Route::get('/agents/statuses',        [AgentController::class, 'statuses'])->name('agents.statuses');
Route::post('/agents/status',         [AgentController::class, 'setStatus'])->name('agents.status');
Route::post('/agents/sync',           [AgentController::class, 'sync'])->name('agents.sync');

// Analytics
Route::get('/analytics',             [AnalyticsController::class, 'index'])->name('analytics.index');
Route::get('/analytics/api',         [AnalyticsController::class, 'api'])->name('analytics.api');

// Recordings
Route::get('/recordings',            [RecordingController::class, 'index'])->name('recordings.index');
Route::get('/recordings/play',       [RecordingController::class, 'play'])->name('recordings.play');

// Blacklist
Route::get('/blacklist',             [BlacklistController::class, 'index'])->name('blacklist.index');
Route::post('/blacklist',            [BlacklistController::class, 'store'])->name('blacklist.store');
Route::delete('/blacklist/{number}', [BlacklistController::class, 'destroy'])->name('blacklist.destroy');
Route::get('/blacklist/check',       [BlacklistController::class, 'check'])->name('blacklist.check');

// Callbacks
Route::get('/callbacks',             [CallbackController::class, 'index'])->name('callbacks.index');
Route::post('/callbacks',            [CallbackController::class, 'store'])->name('callbacks.store');
Route::post('/callbacks/{callback}/attempt', [CallbackController::class, 'attempt'])->name('callbacks.attempt');
Route::post('/callbacks/{callback}/cancel',  [CallbackController::class, 'cancel'])->name('callbacks.cancel');

// Conference
Route::get('/conference',            [ConferenceController::class, 'index'])->name('conference.index');
Route::post('/conference/kick',      [ConferenceController::class, 'kick'])->name('conference.kick');
Route::post('/conference/mute',      [ConferenceController::class, 'mute'])->name('conference.mute');

// IVR Builder
Route::get('/ivr',                   [IVRController::class, 'index'])->name('ivr.index');
Route::get('/ivr/builder',           [IVRController::class, 'builder'])->name('ivr.builder');
Route::post('/ivr/save',             [IVRController::class, 'save'])->name('ivr.save');

// Health
Route::get('/health',                [HealthController::class, 'index'])->name('health.index');
Route::post('/health/check',         [HealthController::class, 'check'])->name('health.check');

// Web Dialer SIP config + debug
Route::get('/dialer/config', [WebDialerController::class, 'config'])->name('dialer.config');
Route::get('/dialer/debug',  function () {
    return view('mikopbx::partials.dialer-debug');
})->name('dialer.debug');
