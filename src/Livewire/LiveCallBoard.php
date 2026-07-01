<?php

namespace BitDreamIT\MikoPBX\Livewire;

use Livewire\Component;
use BitDreamIT\MikoPBX\Services\RestApiService;
use BitDreamIT\MikoPBX\Services\AMIService;

class LiveCallBoard extends Component
{
    public array  $activeCalls   = [];
    public int    $pollInterval  = 5;  // seconds
    public string $selectedCall  = '';
    public string $transferTo    = '';
    public bool   $showTransfer  = false;

    protected $listeners = [
        'echo:mikopbx.calls,incoming' => 'onIncoming',
        'echo:mikopbx.calls,ended'    => 'onEnded',
        'echo:mikopbx.calls,answered' => 'refresh',
    ];

    public function mount(): void
    {
        $this->refresh();
    }

    /**
     * Refresh the active calls list.
     *
     * getActiveCalls() alone does NOT include a usable channel name —
     * its records only have start/answer/src_num/dst_num/linkedid etc.
     * Hangup and Transfer are AMI actions that require the real Asterisk
     * channel name (e.g. "PJSIP/121-00000010"), so we cross-reference
     * getActiveChannels() by matching on the extension/number to resolve
     * the channel for each active call.
     */
    public function refresh(): void
    {
        try {
            $api = app(RestApiService::class);

            $calls    = $api->getActiveCalls()['data'] ?? [];
            $channels = $api->getActiveChannels()['data'] ?? [];

            // Flatten channels into a lookup by extension/number so we can
            // match each call to its real Asterisk channel name.
            $channelList = is_array($channels) && ! array_is_list($channels)
                ? array_values($channels)
                : $channels;

            $this->activeCalls = collect($calls)->map(function ($call) use ($channelList) {
                $channel = $this->resolveChannel($call, $channelList);
                $call['channel'] = $channel;
                return $call;
            })->all();

        } catch (\Throwable) {
            $this->activeCalls = [];
        }
    }

    /**
     * Try to find the matching Asterisk channel for a call from the
     * getActiveChannels() list, matching by source/destination number.
     */
    private function resolveChannel(array $call, array $channelList): string
    {
        // If the call record itself already has a channel field, use it
        if (! empty($call['channel']))  return $call['channel'];
        if (! empty($call['src_chan'])) return $call['src_chan'];

        $srcNum = $call['src_num'] ?? '';
        $dstNum = $call['dst_num'] ?? '';

        foreach ($channelList as $chan) {
            $chanId       = $chan['id'] ?? $chan['channel'] ?? '';
            $chanCallerId = $chan['callerid'] ?? $chan['caller_id_num'] ?? '';
            $chanExten    = $chan['exten'] ?? $chan['extension'] ?? '';

            if ($chanCallerId && ($chanCallerId === $srcNum || $chanCallerId === $dstNum)) {
                return $chanId;
            }
            if ($chanExten && ($chanExten === $srcNum || $chanExten === $dstNum)) {
                return $chanId;
            }
            // Match by extension number appearing in the channel name itself
            // e.g. "PJSIP/121-00000010" contains "121"
            if ($srcNum && str_contains($chanId, "/{$srcNum}-")) return $chanId;
            if ($dstNum && str_contains($chanId, "/{$dstNum}-")) return $chanId;
        }

        return '';
    }

    public function onIncoming(array $data): void
    {
        $this->refresh();
        $this->dispatch('incoming-call-sound');
    }

    public function onEnded(array $data): void
    {
        $this->refresh();
    }

    public function openTransfer(string $channel): void
    {
        if (empty($channel)) {
            $this->dispatch('toast', ['type' => 'error', 'msg' => 'No channel found for this call — cannot transfer.']);
            return;
        }
        $this->selectedCall = $channel;
        $this->showTransfer = true;
    }

    /**
     * Transfer uses AMI (Action: Redirect), not the REST API — MikoPBX
     * REST v3 has no transfer endpoint.
     */
    public function doTransfer(): void
    {
        if (! $this->selectedCall || ! $this->transferTo) return;

        try {
            $ami = app(AMIService::class);
            $ami->connect();
            $result = $ami->redirect($this->selectedCall, $this->transferTo);
            $ami->disconnect();

            $ok = ($result['Response'] ?? '') === 'Success';

            $this->showTransfer = false;
            $this->transferTo   = '';
            $this->dispatch('toast', [
                'type' => $ok ? 'success' : 'error',
                'msg'  => $ok ? 'Call transferred.' : 'Transfer failed: ' . ($result['Message'] ?? 'unknown error'),
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('toast', ['type' => 'error', 'msg' => $e->getMessage()]);
        }

        $this->refresh();
    }

    /**
     * Hangup uses AMI (Action: Hangup), not the REST API — MikoPBX
     * REST v3 has no hangup endpoint.
     */
    public function hangup(string $channel): void
    {
        if (empty($channel)) {
            $this->dispatch('toast', ['type' => 'error', 'msg' => 'No channel found for this call — cannot hang up.']);
            return;
        }

        try {
            $ami = app(AMIService::class);
            $ami->connect();
            $result = $ami->hangup($channel);
            $ami->disconnect();

            $ok = ($result['Response'] ?? '') === 'Success';

            $this->dispatch('toast', [
                'type' => $ok ? 'success' : 'error',
                'msg'  => $ok ? 'Call ended.' : 'Hangup failed: ' . ($result['Message'] ?? 'unknown error'),
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('toast', ['type' => 'error', 'msg' => $e->getMessage()]);
        }
        $this->refresh();
    }

    public function render(): \Illuminate\View\View
    {
        return view('mikopbx::livewire.live-call-board');
    }
}
