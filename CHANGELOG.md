# Changelog

All notable changes to `bitdreamit/laravel-mikopbx` are documented here.

---

## [1.0.0] — 2026-06-18 — Initial Release

### Added

**Core**
- `MikoPBXServiceProvider` — auto-discovery, publishes config/migrations/views
- `MikoPBXManager` — single entry point via `MikoPBX::` facade
- `MikoPBX` facade with full IDE type hints via PHPDoc

**Services**
- `RestApiService` — full MikoPBX REST API v3 client (calls, recordings, campaigns, extensions, CDR)
- `AMIService` — complete Asterisk Manager Interface (originate, transfer, hangup, mute, hold, park, queue, voicemail, conference, AstDB, monitoring, system commands)
- `ARIService` — Asterisk REST Interface (channels, bridges, recordings, playbacks, sounds, endpoints)
- `CampaignService` — auto dialer (broadcast, IVR survey, predictive), campaign CRUD + start/stop/status
- `AgentService` — agent status, click-to-call, transfer, active calls, online/offline
- `RecordingService` — list, download, live recording start/stop/pause/resume, stats
- `ConferenceService` — ARI bridge management, dial-in, mute, kick, record
- `IVRBuilder` — fluent IVR builder with presets (salesSupportTemplate, surveyTemplate)
- `BlacklistService` — block/unblock numbers, expiry, auto-cleanup
- `CallbackService` — schedule/execute/cancel missed call callbacks
- `AnalyticsService` — KPI dashboard, peak hours, agent performance, SLA compliance, abandoned calls, CDR export
- `SmsNotificationService` — Twilio, Vonage, SSL Wireless BD, custom gateway drivers
- `HealthCheckService` — full REST + AMI + extensions + active calls health check

**Events (all ShouldBroadcast)**
- `IncomingCallEvent`
- `CallAnsweredEvent`
- `CallEndedEvent`
- `CallMissedEvent`
- `CallTransferredEvent`
- `AgentStatusChangedEvent`
- `CallerJoinedQueueEvent`
- `CallerLeftQueueEvent`
- `CampaignStartedEvent`
- `CampaignCompletedEvent`
- `ConferenceParticipantJoinedEvent`
- `NewVoicemailEvent`
- `CallRecordedEvent`

**Listeners**
- `HandleIncomingCall`
- `HandleCallEnded`
- `HandleMissedCall` — auto-schedules callback, SMS alert, notification
- `HandleCampaignCompleted` — notifies manager, generates report
- `HandleNewVoicemail` — notifies agent via mail + SMS

**Notifications**
- `MissedCallNotification` — mail, Slack, database
- `VoicemailNotification` — mail, database
- `CampaignCompletedNotification` — mail, database
- `CallbackReminderNotification` — mail, database

**Jobs**
- `ProcessCallbackJob` — retry-safe callback execution
- `SyncExtensionsJob` — sync extension statuses
- `GenerateCampaignReportJob` — generate JSON report after campaign
- `CleanOldCallLogsJob` — purge old CDR records
- `MikoPBXHealthAlertJob` — alert on health degradation
- `CdrDailySyncJob` — daily CDR sync from MikoPBX
- `BlacklistCleanupJob` — clean expired blacklist entries

**HTTP**
- 40+ REST API endpoints
- `CallController`, `CampaignController`, `AgentController`, `RecordingController`
- `ConferenceController`, `IVRController`, `SystemController`
- `AnalyticsController`, `BlacklistController`, `CallbackController`, `HealthController`
- `WebhookController` — HMAC-verified webhook receiver
- Form Request validation classes for all endpoints
- JSON Resource classes with proper formatting

**Middleware**
- `CheckBlacklist` — auto-reject blacklisted numbers
- `VerifyWebhookSignature` — HMAC webhook verification
- `MikoPBXApiAuth` — API key authentication
- `LogCallActivity` — request/response logging

**Models**
- `CallLog` — full call detail record with scopes
- `Extension` — agent/extension with online status
- `Campaign` — campaign with progress tracking
- `CampaignNumber` — per-number status tracking
- `Blacklist` — blocked numbers with expiry
- `CallbackRequest` — callback scheduling with retry
- `Conference` — conference bridge tracking
- `CdrSync` — raw CDR sync storage
- `IVRMenu` — IVR menu persistence

**DTOs**
- `CallDTO` — immutable call data object
- `OriginateDTO` — call origination parameters (fluent)
- `CampaignDTO` — campaign creation parameters
- `AgentDTO` — agent data object

**Enums**
- `CallStatus` — ringing, answered, ended, missed, busy, failed
- `CallDirection` — inbound, outbound, internal
- `HangupCause` — all Asterisk hangup causes with helpers
- `AgentStatus` — registered, unreachable, inuse, ringing

**Traits**
- `HasCallLogs` — add to any Eloquent model for call history + click-to-call
- `FormatsCallDuration` — human-readable duration formatting
- `ValidatesPhoneNumber` — BD phone number normalization + validation

**Contracts (Interfaces)**
- `CallServiceContract`
- `AMIServiceContract`
- `CampaignServiceContract`
- `AgentServiceContract`
- `AnalyticsServiceContract`
- `BlacklistServiceContract`

**Artisan Commands**
- `mikopbx:install` — publish config + migrations
- `mikopbx:listen` — AMI real-time event listener (Supervisor-ready, auto-reconnect)
- `mikopbx:health` — full health check
- `mikopbx:sync-extensions` — sync extension statuses
- `mikopbx:cdr-sync` — CDR sync with date range
- `mikopbx:campaign` — start/stop/status campaigns from CLI

**Scheduler**
- `MikoPBXScheduler::register($schedule)` — all scheduled jobs pre-configured

**Testing**
- `MikoPBXFake` — simulate events, assert calls, factories
- `TestCase` — Orchestra Testbench base for package testing
- Feature tests — call logs, blacklist, callbacks, analytics, IVR
- Unit tests — IVRBuilder, Enums, DTOs, Traits

**Documentation**
- `README.md` — complete usage guide with all examples
- `CHANGELOG.md`
- `docs/supervisor-mikopbx-ami.conf` — production Supervisor config
