<?php

namespace BitDreamIT\MikoPBX\Commands;

use Illuminate\Console\Command;
use BitDreamIT\MikoPBX\Services\RestApiService;
use BitDreamIT\MikoPBX\Models\CallLog;

class CdrSyncCommand extends Command
{
    protected $signature   = 'mikopbx:cdr-sync {--days=1 : How many days back to sync}';
    protected $description = 'Sync CDR (call logs) from MikoPBX REST API v3 into local database';

    public function handle(RestApiService $api): int
    {
        $days = (int) $this->option('days');
        $from = now()->subDays($days)->format('Y-m-d 00:00:00');
        $to   = now()->format('Y-m-d 23:59:59');

        $this->info("Syncing CDR from {$from} to {$to} (last {$days} day(s))...");

        try {
            $offset  = 0;
            $limit   = 100;
            $total   = 0;

            do {
                $response = $api->getCDR($from, $to, [
                    'limit'  => $limit,
                    'offset' => $offset,
                ]);

                // v3 response envelope: { result: true, data: [...] }
                $records = $response['data'] ?? [];

                if (empty($records)) break;

                foreach ($records as $rec) {
                    /*
                     * MikoPBX REST API v3 CDR field names (CdrListItem schema):
                     *   src_num   = caller number  (NOT "src" or "caller")
                     *   dst_num   = called number  (NOT "dst" or "callee")
                     *   UNIQUEID  = unique call ID (capital letters, NOT "uniqueid")
                     *   start     = call start time (NOT "calldate")
                     *   disposition = ANSWERED / NO ANSWER / BUSY / FAILED
                     *   src_chan  = source channel (e.g. PJSIP/101-00000001)
                     *   dst_chan  = destination channel
                     *   billsec   = answered seconds
                     *   duration  = total seconds
                     *   recordingfile = recording filename
                     *   playback_url  = pre-signed stream URL
                     *   download_url  = pre-signed download URL
                     */
                    CallLog::updateOrCreate(
                        // Match on UNIQUEID (uppercase in API response)
                        ['uniqueid' => $rec['UNIQUEID'] ?? $rec['uniqueid'] ?? null],
                        [
                            'caller'         => $rec['src_num']     ?? '',
                            'callee'         => $rec['dst_num']      ?? '',
                            'extension'      => $this->extractExtension($rec['dst_chan'] ?? ''),
                            'channel'        => $rec['src_chan']     ?? '',
                            'direction'      => $this->guessDirection($rec),
                            'status'         => $this->mapDisposition($rec['disposition'] ?? ''),
                            'duration'       => (int) ($rec['duration'] ?? 0),
                            'billsec'        => (int) ($rec['billsec']  ?? 0),
                            'recording_file' => $rec['recordingfile'] ?? null,
                            'recording_url'  => $rec['playback_url']  ?? null,
                            'linkedid'       => $rec['linkedid']     ?? null,
                            'started_at'     => $rec['start']        ?? null,
                            'answered_at'    => ! empty($rec['answer']) ? $rec['answer'] : null,
                            'ended_at'       => $rec['endtime']      ?? null,
                        ]
                    );
                    $total++;
                }

                $offset += $limit;

                // Stop if fewer records than limit (last page)
            } while (count($records) === $limit);

            $this->info("✅ Synced {$total} CDR records.");

        } catch (\Throwable $e) {
            $this->error("❌ CDR sync failed: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }

    /**
     * Extract extension number from a channel name.
     * e.g. "PJSIP/101-00000001" → "101"
     *      "SIP/102-abc"        → "102"
     */
    private function extractExtension(string $channel): string
    {
        if (empty($channel)) return '';
        // Channel format: PJSIP/EXTENSION-XXXXXXXX or SIP/EXTENSION-XXXXXXXX
        if (preg_match('/^(?:PJSIP|SIP)\/(\w+)-/i', $channel, $m)) {
            return $m[1];
        }
        return $channel;
    }

    /**
     * Guess call direction from CDR record.
     * MikoPBX v3 CDR does not have an explicit "direction" field.
     * We infer from is_app, from_account, src_num patterns.
     */
    private function guessDirection(array $rec): string
    {
        // If is_app is set, it is likely an internal/system call
        if (! empty($rec['is_app']) && $rec['is_app'] !== '0') {
            return 'internal';
        }
        // If dst_num is numeric short (≤4 digits), likely internal
        $dst = $rec['dst_num'] ?? '';
        if ($dst && strlen($dst) <= 4 && ctype_digit($dst)) {
            return 'internal';
        }
        // Default: inbound (you can extend this logic based on your DID/trunk setup)
        return 'inbound';
    }

    /**
     * Map MikoPBX disposition values to our status strings.
     * MikoPBX v3 uses: ANSWERED, NO ANSWER, BUSY, FAILED
     */
    private function mapDisposition(string $disposition): string
    {
        return match(strtoupper(trim($disposition))) {
            'ANSWERED'  => 'answered',
            'NO ANSWER' => 'missed',
            'BUSY'      => 'busy',
            'FAILED'    => 'failed',
            default     => 'ended',
        };
    }
}
