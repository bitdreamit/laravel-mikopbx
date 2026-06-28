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
            $offset = 0;
            $limit  = 100;
            $total  = 0;

            do {
                $response = $api->getCDR($from, $to, [
                    'limit'  => $limit,
                    'offset' => $offset,
                ]);

                /*
                 * REAL MikoPBX v3 CDR response structure:
                 *
                 * {
                 *   "result": true,
                 *   "data": {
                 *     "records": [          ← grouped call records (one per linkedid)
                 *       {
                 *         "linkedid":       "mikopbx-xxx.8",
                 *         "start":          "2026-06-26 20:44:56.147",
                 *         "src_num":        "121",
                 *         "dst_num":        "+880...",
                 *         "disposition":    "NOANSWER",    ← top-level summary
                 *         "totalDuration":  20,
                 *         "totalBillsec":   0,
                 *         "records": [      ← individual channel records inside
                 *           {
                 *             "id":            667,
                 *             "UNIQUEID":      "mikopbx-xxx.8_g9l0io",
                 *             "src_chan":      "PJSIP/121-00000008",
                 *             "dst_chan":      "PJSIP/SIP-TRUNK-...",
                 *             "disposition":   "NOANSWER",
                 *             "recordingfile": "/storage/.../xxx.webm",
                 *             "playback_url":  "/pbxcore/api/v3/cdr:playback?token=...",
                 *             "download_url":  "/pbxcore/api/v3/cdr:download?token=...",
                 *             ...
                 *           }
                 *         ]
                 *       }
                 *     ],
                 *     "pagination": {
                 *       "total":   359,
                 *       "limit":   100,
                 *       "offset":  0,
                 *       "hasMore": true,
                 *       "lastId":  563
                 *     }
                 *   }
                 * }
                 *
                 * disposition values: ANSWERED | NOANSWER | BUSY | FAILED
                 * (NOT "NO ANSWER" — that was wrong in old docs)
                 */

                $dataBlock = $response['data'] ?? [];

                // Extract grouped records array
                $groupedRecords = $dataBlock['records'] ?? [];

                if (empty($groupedRecords)) {
                    $this->line('  No records in this page.');
                    break;
                }

                foreach ($groupedRecords as $group) {
                    /*
                     * Each $group is one call (identified by linkedid).
                     * Inside $group['records'] are the individual channel legs.
                     * We save ONE row per inner record (each has its own UNIQUEID).
                     */
                    $innerRecords = $group['records'] ?? [];

                    // If no inner records, fall back to using the group itself
                    if (empty($innerRecords)) {
                        $innerRecords = [$group];
                    }

                    foreach ($innerRecords as $rec) {
                        $uniqueid = $rec['UNIQUEID'] ?? $rec['uniqueid'] ?? null;

                        // Skip if no unique ID
                        if (empty($uniqueid)) continue;

                        // Merge group-level fields with inner record
                        // (inner record fields take priority)
                        $merged = array_merge([
                            'src_num'     => $group['src_num']     ?? '',
                            'dst_num'     => $group['dst_num']     ?? '',
                            'src_name'    => $group['src_name']    ?? '',
                            'dst_name'    => $group['dst_name']    ?? '',
                            'disposition' => $group['disposition'] ?? '',
                            'linkedid'    => $group['linkedid']    ?? '',
                            'start'       => $group['start']       ?? '',
                        ], $rec);

                        CallLog::updateOrCreate(
                            ['uniqueid' => $uniqueid],
                            [
                                'linkedid'       => $merged['linkedid']    ?? null,
                                'caller'         => $merged['src_num']     ?? '',
                                'callee'         => $merged['dst_num']     ?? '',
                                'extension'      => $this->extractExtension($merged['src_chan'] ?? ''),
                                'channel'        => $merged['src_chan']    ?? '',
                                'direction'      => $this->guessDirection($merged),
                                'status'         => $this->mapDisposition($merged['disposition'] ?? ''),
                                'duration'       => (int) ($merged['duration'] ?? $merged['totalDuration'] ?? 0),
                                'billsec'        => (int) ($merged['billsec']  ?? $merged['totalBillsec']  ?? 0),
                                'recording_file' => ! empty($merged['recordingfile'])
                                                        ? basename($merged['recordingfile'])
                                                        : null,
                                'recording_url'  => ! empty($merged['playback_url'])
                                                        ? $merged['playback_url']
                                                        : null,
                                'started_at'     => $this->parseDate($merged['start']    ?? ''),
                                'answered_at'    => $this->parseDate($merged['answer']   ?? ''),
                                'ended_at'       => $this->parseDate($merged['endtime']  ?? ''),
                            ]
                        );

                        $total++;
                    }
                }

                // Pagination — use hasMore from pagination block
                $pagination = $dataBlock['pagination'] ?? [];
                $hasMore    = $pagination['hasMore'] ?? false;
                $offset    += $limit;

                $paginationTotal = $pagination['total'] ?? '?';
                $this->line("  Processed page offset={$offset}, total API records={$paginationTotal}");

                if (! $hasMore) break;

            } while (true);

            $this->info("✅ Synced {$total} CDR channel records.");

        } catch (\Throwable $e) {
            $this->error("❌ CDR sync failed: {$e->getMessage()}");
            $this->error($e->getTraceAsString());
            return 1;
        }

        return 0;
    }

    /**
     * Extract extension number from a SIP channel name.
     *
     * "PJSIP/121-00000008"          → "121"
     * "PJSIP/SIP-TRUNK-7627EF24-x"  → skip (trunk, not extension)
     * "SIP/101-abc"                  → "101"
     */
    private function extractExtension(string $channel): string
    {
        if (empty($channel)) return '';

        // Skip trunk channels (contain "TRUNK" or "SIP-" prefix after PJSIP/)
        if (stripos($channel, 'TRUNK') !== false) return '';
        if (preg_match('/^(?:PJSIP|SIP)\/SIP-/i', $channel)) return '';

        // Extract extension: PJSIP/101-00000001 → 101
        if (preg_match('/^(?:PJSIP|SIP)\/(\w+)-/i', $channel, $m)) {
            return $m[1];
        }

        return '';
    }

    /**
     * Determine call direction from MikoPBX v3 CDR fields.
     *
     * Logic based on real data patterns:
     *   from_account = extension number (e.g. "121") → outbound
     *   to_account   = "SIP-TRUNK-xxx"               → outbound (to trunk)
     *   dst_num      = short number ≤4 digits         → internal
     *   src_num      = short number, dst_num = long   → outbound
     *   otherwise                                     → inbound
     */
    private function guessDirection(array $rec): string
    {
        $srcNum     = $rec['src_num']     ?? '';
        $dstNum     = $rec['dst_num']     ?? '';
        $toAccount  = $rec['to_account']  ?? '';
        $fromAccount= $rec['from_account']?? '';
        $isApp      = $rec['is_app']      ?? '';
        $appName    = $rec['appname']     ?? '';

        // System/app calls (voicemail, conferences etc.)
        if (! empty($isApp) && $isApp !== '0' && $isApp !== '') return 'internal';
        if (! empty($appName)) return 'internal';

        // Both src and dst are short — internal extension-to-extension
        if ($srcNum && strlen($srcNum) <= 6 && ctype_digit($srcNum)
            && $dstNum && strlen($dstNum) <= 6 && ctype_digit($dstNum)) {
            return 'internal';
        }

        // Going to a SIP trunk → outbound
        if (stripos($toAccount, 'TRUNK') !== false || stripos($toAccount, 'SIP-') !== false) {
            return 'outbound';
        }

        // from_account is a short extension number, dst is a long number → outbound
        if ($fromAccount && ctype_digit($fromAccount) && strlen($fromAccount) <= 6
            && strlen($dstNum) > 7) {
            return 'outbound';
        }

        return 'inbound';
    }

    /**
     * Map MikoPBX v3 disposition to our status values.
     *
     * Real API values (from actual response):
     *   ANSWERED  → answered
     *   NOANSWER  → missed   (NOT "NO ANSWER" — no space in real data)
     *   BUSY      → busy
     *   FAILED    → failed
     */
    private function mapDisposition(string $disposition): string
    {
        return match(strtoupper(trim($disposition))) {
            'ANSWERED'              => 'answered',
            'NOANSWER', 'NO ANSWER'=> 'missed',   // handle both formats
            'BUSY'                  => 'busy',
            'FAILED'                => 'failed',
            default                 => 'ended',
        };
    }

    /**
     * Parse a MikoPBX datetime string safely.
     * Handles: "2026-06-26 20:44:56.147" (with microseconds)
     * Returns null for empty strings.
     */
    private function parseDate(string $value): ?string
    {
        if (empty(trim($value))) return null;

        // Strip microseconds for MySQL compatibility: "20:44:56.147" → "20:44:56"
        $clean = preg_replace('/(\d{2}:\d{2}:\d{2})\.\d+/', '$1', $value);

        return $clean ?: null;
    }
}
