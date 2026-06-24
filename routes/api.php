<?php

use Illuminate\Support\Facades\Route;
use BitDreamIT\MikoPBX\Http\Controllers\{
    CallController, AgentController, AnalyticsController, CampaignController
};

// These mirror the web routes but return JSON — used by Alpine.js / Livewire AJAX
Route::get('/active-calls',             [CallController::class,    'active']);
Route::post('/originate',               [CallController::class,    'originate']);
Route::post('/transfer',                [CallController::class,    'transfer']);
Route::post('/hangup',                  [CallController::class,    'hangup']);
Route::post('/mute',                    [CallController::class,    'mute']);
Route::get('/agent-statuses',           [AgentController::class,   'statuses']);
Route::post('/agent-status',            [AgentController::class,   'setStatus']);
Route::get('/analytics',                [AnalyticsController::class,'api']);
Route::get('/campaign/{campaign}/progress', [CampaignController::class,'progress']);
