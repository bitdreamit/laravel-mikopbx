<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $p = config('mikopbx.table_prefix', 'mikopbx_');

        // ── Extensions / Agents ─────────────────────────────────────────────
        Schema::create("{$p}extensions", function (Blueprint $t) {
            $t->id();
            $t->string('extension', 20)->unique();
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('mobile')->nullable();
            $t->string('sip_peer')->nullable();
            $t->enum('status', ['online', 'offline', 'busy', 'dnd', 'away'])->default('offline');
            $t->enum('role', ['agent', 'supervisor', 'admin'])->default('agent');
            $t->boolean('active')->default(true);
            $t->json('meta')->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamps();
        });

        // ── Call Logs (CDR) ──────────────────────────────────────────────────
        Schema::create("{$p}call_logs", function (Blueprint $t) {
            $t->id();
            $t->string('uniqueid', 64)->unique()->nullable();
            $t->string('linkedid', 64)->nullable()->index();
            $t->string('caller', 30)->index();
            $t->string('callee', 30)->nullable();
            $t->string('extension', 20)->nullable()->index();
            $t->string('channel', 100)->nullable()->index();
            $t->enum('direction', ['inbound', 'outbound', 'internal'])->default('inbound');
            $t->enum('status', ['ringing', 'answered', 'missed', 'busy', 'failed', 'voicemail', 'transferred', 'ended'])->default('ringing');
            $t->string('cause', 60)->nullable();
            $t->unsignedInteger('duration')->default(0);       // seconds
            $t->unsignedInteger('billsec')->default(0);        // answered seconds
            $t->string('recording_file')->nullable();
            $t->string('recording_url')->nullable();
            $t->foreignId('campaign_id')->nullable()->constrained("{$p}campaigns")->nullOnDelete();
            $t->foreignId('callback_id')->nullable();
            $t->json('meta')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('answered_at')->nullable();
            $t->timestamp('ended_at')->nullable();
            $t->timestamps();
        });

        // ── Campaigns ────────────────────────────────────────────────────────
        Schema::create("{$p}campaigns", function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->enum('type', ['agent_connect', 'voice_broadcast', 'survey', 'sms_blast'])->default('agent_connect');
            $t->enum('status', ['draft', 'running', 'paused', 'completed', 'failed'])->default('draft');
            $t->string('audio_file')->nullable();
            $t->string('audio_url')->nullable();
            $t->unsignedTinyInteger('max_channels')->default(5);
            $t->unsignedTinyInteger('retry_attempts')->default(3);
            $t->unsignedSmallInteger('retry_delay')->default(300);
            $t->unsignedSmallInteger('dial_timeout')->default(30);
            $t->string('caller_id')->nullable();
            $t->string('destination_extension')->nullable();
            $t->json('ivr_script')->nullable();
            $t->unsignedInteger('mikopbx_task_id')->nullable();
            $t->unsignedInteger('total_numbers')->default(0);
            $t->unsignedInteger('dialed')->default(0);
            $t->unsignedInteger('answered')->default(0);
            $t->unsignedInteger('failed')->default(0);
            $t->unsignedInteger('retrying')->default(0);
            $t->timestamp('scheduled_at')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->json('meta')->nullable();
            $t->timestamps();
        });

        // ── Campaign Numbers ─────────────────────────────────────────────────
        Schema::create("{$p}campaign_numbers", function (Blueprint $t) {
            $t->id();
            $t->foreignId('campaign_id')->constrained("{$p}campaigns")->cascadeOnDelete();
            $t->string('number', 30)->index();
            $t->string('name')->nullable();
            $t->enum('status', ['pending', 'dialing', 'answered', 'no_answer', 'busy', 'failed', 'opted_out'])->default('pending');
            $t->unsignedTinyInteger('attempt')->default(0);
            $t->string('dtmf_response')->nullable();
            $t->unsignedSmallInteger('duration')->default(0);
            $t->timestamp('last_attempted_at')->nullable();
            $t->timestamp('next_attempt_at')->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();
        });

        // ── Blacklist ────────────────────────────────────────────────────────
        Schema::create("{$p}blacklist", function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->string('reason')->nullable();
            $t->enum('direction', ['inbound', 'outbound', 'both'])->default('both');
            $t->timestamp('expires_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // ── Callbacks ────────────────────────────────────────────────────────
        Schema::create("{$p}callbacks", function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->index();
            $t->string('name')->nullable();
            $t->string('note')->nullable();
            $t->enum('status', ['pending', 'in_progress', 'completed', 'cancelled', 'failed'])->default('pending');
            $t->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $t->foreignId('assigned_to')->nullable()->constrained("{$p}extensions")->nullOnDelete();
            $t->foreignId('call_log_id')->nullable()->constrained("{$p}call_logs")->nullOnDelete();
            $t->timestamp('scheduled_at')->nullable();
            $t->timestamp('attempted_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->timestamps();
        });

        // ── IVR Trees ────────────────────────────────────────────────────────
        Schema::create("{$p}ivr_trees", function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('description')->nullable();
            $t->json('nodes');     // full IVR node graph
            $t->boolean('active')->default(false);
            $t->string('greeting_audio')->nullable();
            $t->unsignedTinyInteger('timeout_seconds')->default(5);
            $t->unsignedTinyInteger('max_retries')->default(3);
            $t->timestamps();
        });

        // ── Conference Rooms ─────────────────────────────────────────────────
        Schema::create("{$p}conference_rooms", function (Blueprint $t) {
            $t->id();
            $t->string('room_number', 20)->unique();
            $t->string('name');
            $t->string('pin')->nullable();
            $t->string('admin_pin')->nullable();
            $t->boolean('record')->default(false);
            $t->boolean('mute_on_join')->default(false);
            $t->unsignedTinyInteger('max_participants')->default(10);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        // ── Agent Status Log ─────────────────────────────────────────────────
        Schema::create("{$p}agent_status_log", function (Blueprint $t) {
            $t->id();
            $t->foreignId('extension_id')->constrained("{$p}extensions")->cascadeOnDelete();
            $t->string('from_status', 20);
            $t->string('to_status', 20);
            $t->unsignedInteger('duration')->default(0);
            $t->timestamp('changed_at');
            $t->timestamps();
        });

        // ── Health Check Log ─────────────────────────────────────────────────
        Schema::create("{$p}health_logs", function (Blueprint $t) {
            $t->id();
            $t->enum('status', ['healthy', 'degraded', 'critical'])->default('healthy');
            $t->boolean('ami_connected')->default(false);
            $t->boolean('ari_connected')->default(false);
            $t->boolean('sip_trunk_up')->default(false);
            $t->unsignedSmallInteger('active_calls')->default(0);
            $t->unsignedSmallInteger('extensions_online')->default(0);
            $t->json('details')->nullable();
            $t->timestamp('checked_at');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        $p = config('mikopbx.table_prefix', 'mikopbx_');
        foreach ([
            "{$p}health_logs", "{$p}agent_status_log", "{$p}conference_rooms",
            "{$p}ivr_trees", "{$p}callbacks", "{$p}blacklist",
            "{$p}campaign_numbers", "{$p}call_logs", "{$p}campaigns", "{$p}extensions",
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
