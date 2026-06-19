<?php

namespace BitDreamIT\MikoPBX\Services;

use BitDreamIT\MikoPBX\Models\CallLog;

/**
 * Conference Service
 *
 * Manage multi-party conference calls via ARI bridges.
 *
 * Usage:
 *   $conf = $conference->create('Team Meeting');
 *   $conference->addParticipant($conf['id'], 'PJSIP/101');
 *   $conference->addParticipant($conf['id'], 'PJSIP/102');
 *   $conference->startRecording($conf['id']);
 *   $conference->muteParticipant($conf['id'], 'PJSIP/101');
 *   $conference->end($conf['id']);
 */
class ConferenceService
{
    public function __construct(private ARIService $ari) {}

    // ─────────────────────────────────────────
    // BRIDGE MANAGEMENT
    // ─────────────────────────────────────────

    public function create(string $name = ''): array
    {
        return $this->ari->createBridge('mixing,dtmf_events', $name ?: 'conf-' . now()->timestamp);
    }

    public function end(string $bridgeId): array
    {
        return $this->ari->destroyBridge($bridgeId);
    }

    public function getAll(): array
    {
        return $this->ari->getBridges();
    }

    public function get(string $bridgeId): array
    {
        return $this->ari->getBridge($bridgeId);
    }

    // ─────────────────────────────────────────
    // PARTICIPANTS
    // ─────────────────────────────────────────

    public function addParticipant(string $bridgeId, string $channelId): array
    {
        return $this->ari->addChannelToBridge($bridgeId, $channelId);
    }

    public function removeParticipant(string $bridgeId, string $channelId): array
    {
        return $this->ari->removeChannelFromBridge($bridgeId, $channelId);
    }

    /** Dial in a new number and add to conference */
    public function dialIn(
        string $bridgeId,
        string $endpoint,
        string $callerId = '',
        string $context  = 'from-internal'
    ): array {
        // Originate channel
        $channel = $this->ari->originateChannel(
            $endpoint,
            's',
            $context,
            $callerId
        );

        // Add to bridge
        if (!empty($channel['id'])) {
            $this->ari->addChannelToBridge($bridgeId, $channel['id']);
        }

        return $channel;
    }

    // ─────────────────────────────────────────
    // MUTE / UNMUTE
    // ─────────────────────────────────────────

    public function muteParticipant(string $channelId, string $direction = 'in'): array
    {
        return $this->ari->muteChannel($channelId, $direction);
    }

    public function unmuteParticipant(string $channelId, string $direction = 'in'): array
    {
        return $this->ari->unmuteChannel($channelId, $direction);
    }

    // ─────────────────────────────────────────
    // RECORDING
    // ─────────────────────────────────────────

    public function startRecording(string $bridgeId, string $name = ''): array
    {
        $name = $name ?: 'conf-recording-' . now()->format('Y-m-d-His');
        return $this->ari->recordBridge($bridgeId, $name);
    }

    public function stopRecording(string $recordingName): array
    {
        return $this->ari->stopRecording($recordingName);
    }

    // ─────────────────────────────────────────
    // ANNOUNCEMENTS
    // ─────────────────────────────────────────

    public function playToAll(string $bridgeId, string $audioFile): array
    {
        return $this->ari->playAudioToBridge($bridgeId, $audioFile);
    }
}
