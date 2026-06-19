<?php
// database/migrations/2026_01_01_000001_create_mikopbx_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Call Logs ─────────────────────────────────────────────
        Schema::create('mikopbx_call_logs', function (Blueprint $t) {
            $t->id();
            $t->string('caller')->nullable()->index();
            $t->string('caller_name')->nullable();
            $t->string('extension')->nullable()->index();
            $t->string('channel')->nullable()->index();
            $t->string('destination')->nullable();
            $t->enum('direction', ['inbound', 'outbound', 'internal'])->default('inbound');
            $t->enum('status', ['ringing', 'answered', 'ended', 'missed', 'busy', 'failed'])->default('ringing');
            $t->string('cause')->nullable();
            $t->unsignedInteger('duration')->default(0);
            $t->string('recording_file')->nullable();
            $t->string('queue')->nullable();
            $t->string('ivr_key_pressed')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('answered_at')->nullable();
            $t->timestamp('ended_at')->nullable();
            $t->timestamps();
            $t->index(['caller', 'started_at']);
            $t->index(['extension', 'started_at']);
            $t->index(['status', 'direction']);
        });

        // ── Extensions ────────────────────────────────────────────
        Schema::create('mikopbx_extensions', function (Blueprint $t) {
            $t->id();
            $t->string('number', 20)->unique();
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('sip_peer')->nullable();
            $t->string('department')->nullable();
            $t->boolean('online')->default(false);
            $t->string('status')->default('UNREACHABLE');
            $t->string('current_channel')->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamps();
        });

        // ── Campaigns ─────────────────────────────────────────────
        Schema::create('mikopbx_campaigns', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->unsignedInteger('mikopbx_task_id')->nullable();
            $t->string('audio_file')->nullable();
            $t->string('type')->default('broadcast'); // broadcast | ivr_survey | predictive
            $t->unsignedTinyInteger('max_channels')->default(5);
            $t->string('status')->default('created');
            $t->unsignedInteger('total_numbers')->default(0);
            $t->unsignedInteger('dialed_count')->default(0);
            $t->unsignedInteger('answered_count')->default(0);
            $t->unsignedInteger('missed_count')->default(0);
            $t->unsignedInteger('failed_count')->default(0);
            $t->json('ivr_options')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('scheduled_at')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('stopped_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();
        });

        // ── Campaign Numbers ──────────────────────────────────────
        Schema::create('mikopbx_campaign_numbers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('campaign_id')->constrained('mikopbx_campaigns')->cascadeOnDelete();
            $t->string('number', 20)->index();
            $t->string('name')->nullable();
            $t->enum('status', ['pending', 'dialing', 'answered', 'missed', 'failed', 'skipped'])->default('pending');
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->string('ivr_response')->nullable();
            $t->unsignedInteger('duration')->default(0);
            $t->string('recording_file')->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('last_attempt_at')->nullable();
            $t->timestamps();
            $t->index(['campaign_id', 'status']);
        });

        // ── Callbacks ─────────────────────────────────────────────
        Schema::create('mikopbx_callbacks', function (Blueprint $t) {
            $t->id();
            $t->string('caller_number')->index();
            $t->string('caller_name')->nullable();
            $t->string('extension')->nullable();
            $t->string('queue')->nullable();
            $t->string('reason')->default('missed_call');
            $t->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->unsignedTinyInteger('max_attempts')->default(3);
            $t->timestamp('scheduled_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['status', 'scheduled_at']);
        });

        // ── Conferences ───────────────────────────────────────────
        Schema::create('mikopbx_conferences', function (Blueprint $t) {
            $t->id();
            $t->string('bridge_id')->nullable()->unique();
            $t->string('name');
            $t->string('status')->default('active');
            $t->string('recording_name')->nullable();
            $t->json('participants')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('ended_at')->nullable();
            $t->unsignedInteger('duration')->default(0);
            $t->timestamps();
        });

        // ── Blacklist ─────────────────────────────────────────────
        Schema::create('mikopbx_blacklist', function (Blueprint $t) {
            $t->id();
            $t->string('number', 20)->unique();
            $t->string('reason')->nullable();
            $t->string('added_by')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        // ── IVR Menus ─────────────────────────────────────────────
        Schema::create('mikopbx_ivr_menus', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('greeting_file')->nullable();
            $t->unsignedTinyInteger('timeout')->default(10);
            $t->unsignedTinyInteger('max_invalid')->default(3);
            $t->json('keypresses');
            $t->string('timeout_action')->default('repeat');
            $t->string('invalid_action')->default('repeat');
            $t->boolean('active')->default(true);
            $t->unsignedInteger('mikopbx_id')->nullable();
            $t->timestamps();
        });

        // ── CDR Sync (for reporting) ───────────────────────────────
        Schema::create('mikopbx_cdr_sync', function (Blueprint $t) {
            $t->id();
            $t->string('uniqueid')->unique();
            $t->string('src')->nullable();
            $t->string('dst')->nullable();
            $t->string('dcontext')->nullable();
            $t->string('clid')->nullable();
            $t->string('channel')->nullable();
            $t->string('dstchannel')->nullable();
            $t->string('lastapp')->nullable();
            $t->string('lastdata')->nullable();
            $t->timestamp('calldate')->nullable();
            $t->unsignedInteger('duration')->default(0);
            $t->unsignedInteger('billsec')->default(0);
            $t->string('disposition')->nullable();
            $t->string('amaflags')->nullable();
            $t->string('accountcode')->nullable();
            $t->string('userfield')->nullable();
            $t->string('recordingfile')->nullable();
            $t->timestamps();
            $t->index(['src', 'calldate']);
            $t->index(['dst', 'calldate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mikopbx_cdr_sync');
        Schema::dropIfExists('mikopbx_ivr_menus');
        Schema::dropIfExists('mikopbx_blacklist');
        Schema::dropIfExists('mikopbx_conferences');
        Schema::dropIfExists('mikopbx_callbacks');
        Schema::dropIfExists('mikopbx_campaign_numbers');
        Schema::dropIfExists('mikopbx_campaigns');
        Schema::dropIfExists('mikopbx_extensions');
        Schema::dropIfExists('mikopbx_call_logs');
    }
};
