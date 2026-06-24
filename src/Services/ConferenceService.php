<?php
namespace BitDreamIT\MikoPBX\Services;

class ConferenceService
{
    public function __construct(private RestApiService $api, private AMIService $ami) {}

    public function getRooms(): array { return $this->api->getConferenceRooms(); }

    public function addParticipant(string $channel, string $room): array
    {
        return $this->ami->action([
            'Action'      => 'ConfbridgeKick',
            'Conference'  => $room,
            'Channel'     => $channel,
        ]);
    }

    public function kickParticipant(string $channel, string $room): array
    {
        return $this->ami->action([
            'Action'     => 'ConfbridgeKick',
            'Conference' => $room,
            'Channel'    => $channel,
        ]);
    }

    public function muteParticipant(string $channel, string $room): array
    {
        return $this->ami->action([
            'Action'     => 'ConfbridgeMute',
            'Conference' => $room,
            'Channel'    => $channel,
        ]);
    }
}
