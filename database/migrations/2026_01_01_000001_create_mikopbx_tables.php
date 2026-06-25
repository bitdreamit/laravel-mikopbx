<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		$p = config('mikopbx.table_prefix', 'mikopbx_');

		// 1. Extensions/Agents (no dependencies)
		Schema::create("{$p}extensions", function (Blueprint $t) use ($p) {
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

		// 2. Campaigns (depends on users)
		Schema::create("{$p}campaigns", function (Blueprint $t) use ($p) {
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
			$t->unsignedBigInteger('created_by')->nullable();
			$t->json('meta')->nullable();
			$t->timestamps();
		});

		// 3. Call Logs/CDR (depends on campaigns)
		Schema::create("{$p}call_logs", function (Blueprint $t) use ($p) {
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
			$t->unsignedInteger('duration')->default(0);
			$t->unsignedInteger('billsec')->default(0);
			$t->string('recording_file')->nullable();
			$t->string('recording_url')->nullable();
			$t->unsignedBigInteger('campaign_id')->nullable();
			$t->foreign('campaign_id')->references('id')->on("{$p}campaigns")->nullOnDelete();
			$t->unsignedBigInteger('callback_id')->nullable();
			$t->json('meta')->nullable();
			$t->timestamp('started_at')->nullable();
			$t->timestamp('answered_at')->nullable();
			$t->timestamp('ended_at')->nullable();
			$t->timestamps();
		});

		// 4. Campaign Numbers (depends on campaigns)
		Schema::create("{$p}campaign_numbers", function (Blueprint $t) use ($p) {
			$t->id();
			$t->unsignedBigInteger('campaign_id');
			$t->foreign('campaign_id')->references('id')->on("{$p}campaigns")->cascadeOnDelete();
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

		// 5. Blacklist (depends on users)
		Schema::create("{$p}blacklist", function (Blueprint $t) use ($p) {
			$t->id();
			$t->string('number', 30)->unique();
			$t->string('reason')->nullable();
			$t->enum('direction', ['inbound', 'outbound', 'both'])->default('both');
			$t->timestamp('expires_at')->nullable();
			$t->unsignedBigInteger('created_by')->nullable();
			$t->timestamps();
		});

		// 6. Callbacks (depends on extensions and call_logs)
		Schema::create("{$p}callbacks", function (Blueprint $t) use ($p) {
			$t->id();
			$t->string('number', 30)->index();
			$t->string('name')->nullable();
			$t->string('note')->nullable();
			$t->enum('status', ['pending', 'in_progress', 'completed', 'cancelled', 'failed'])->default('pending');
			$t->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
			$t->unsignedBigInteger('assigned_to')->nullable();
			$t->foreign('assigned_to')->references('id')->on("{$p}extensions")->nullOnDelete();
			$t->unsignedBigInteger('call_log_id')->nullable();
			$t->foreign('call_log_id')->references('id')->on("{$p}call_logs")->nullOnDelete();
			$t->timestamp('scheduled_at')->nullable();
			$t->timestamp('attempted_at')->nullable();
			$t->timestamp('completed_at')->nullable();
			$t->unsignedBigInteger('created_by')->nullable();
			$t->timestamps();
		});

		// 7. IVR Trees (no dependencies)
		Schema::create("{$p}ivr_trees", function (Blueprint $t) use ($p) {
			$t->id();
			$t->string('name');
			$t->string('description')->nullable();
			$t->json('nodes');
			$t->boolean('active')->default(false);
			$t->string('greeting_audio')->nullable();
			$t->unsignedTinyInteger('timeout_seconds')->default(5);
			$t->unsignedTinyInteger('max_retries')->default(3);
			$t->timestamps();
		});

		// 8. Conference Rooms (no dependencies)
		Schema::create("{$p}conference_rooms", function (Blueprint $t) use ($p) {
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

		// 9. Agent Status Log (depends on extensions)
		Schema::create("{$p}agent_status_log", function (Blueprint $t) use ($p) {
			$t->id();
			$t->unsignedBigInteger('extension_id');
			$t->foreign('extension_id')->references('id')->on("{$p}extensions")->cascadeOnDelete();
			$t->string('from_status', 20);
			$t->string('to_status', 20);
			$t->unsignedInteger('duration')->default(0);
			$t->timestamp('changed_at');
			$t->timestamps();
		});

		// 10. Health Check Log (no dependencies)
		Schema::create("{$p}health_logs", function (Blueprint $t) use ($p) {
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

		Schema::dropIfExists("{$p}health_logs");
		Schema::dropIfExists("{$p}agent_status_log");
		Schema::dropIfExists("{$p}conference_rooms");
		Schema::dropIfExists("{$p}ivr_trees");
		Schema::dropIfExists("{$p}callbacks");
		Schema::dropIfExists("{$p}blacklist");
		Schema::dropIfExists("{$p}campaign_numbers");
		Schema::dropIfExists("{$p}call_logs");
		Schema::dropIfExists("{$p}campaigns");
		Schema::dropIfExists("{$p}extensions");
	}
};