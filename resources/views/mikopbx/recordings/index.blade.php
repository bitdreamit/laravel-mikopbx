@extends('mikopbx::layouts.app')
@section('title','Recordings')
@section('heading','Call Recordings')

@section('content')
{{-- recordingPlayer() is defined in layouts/app.blade.php head --}}
<div class="space-y-4" x-data="recordingPlayer()">

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div>
                <label class="text-xs text-gray-500 block mb-1">From</label>
                <input type="date" name="from" value="{{ $from }}" class="input text-sm">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">To</label>
                <input type="date" name="to" value="{{ $to }}" class="input text-sm">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">Caller Number</label>
                <input type="text" name="number" value="{{ $num }}"
                       placeholder="e.g. 01711000000" class="input text-sm">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="btn-primary flex-1 justify-center">Search</button>
                <a href="{{ route('mikopbx.recordings.index') }}" class="btn-secondary">Clear</a>
            </div>
        </div>
    </form>

    {{-- Recording list --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="border-b border-gray-100 px-4 py-3 flex items-center justify-between">
            <h3 class="font-semibold text-gray-900 text-sm">Recordings</h3>
            <span class="text-xs text-gray-400">{{ count($recordings) }} found</span>
        </div>

        @if(empty($recordings))
        <div class="px-4 py-12 text-center text-sm text-gray-400">
            <div class="text-3xl mb-3">🎙️</div>
            No recordings found for this period.
        </div>
        @else
        <div class="divide-y divide-gray-50">
            @foreach($recordings as $rec)
            @php
                /*
                 * Real MikoPBX v3 CDR field names (from actual API response):
                 *   src_num      — caller number (e.g. "121")
                 *   dst_num      — called number (e.g. "+8801303809806")
                 *   start        — "2026-06-26 20:37:18.896"
                 *   billsec      — answered seconds (integer)
                 *   playback_url — "/pbxcore/api/v3/cdr:playback?token=abc123"
                 *   download_url — "/pbxcore/api/v3/cdr:download?token=abc123"
                 *   recordingfile — "/storage/usbdisk1/.../mikopbx-xxx.webm"
                 *   UNIQUEID     — "mikopbx-1782484638.4_Vo6697"
                 *   disposition  — "ANSWERED" / "NOANSWER"
                 *   src_name     — caller display name (if available)
                 */
                $caller      = $rec['src_num']  ?? $rec['caller'] ?? 'Unknown';
                $callee      = $rec['dst_num']  ?? $rec['callee'] ?? '';
                $callerName  = $rec['src_name'] ?? '';
                $date        = $rec['start']    ?? $rec['calldate'] ?? '';
                $duration    = (int) ($rec['billsec']  ?? $rec['duration'] ?? 0);
                $playbackUrl = $rec['playback_url'] ?? '';
                $downloadUrl = $rec['download_url'] ?? '';
                $filename    = basename($rec['recordingfile'] ?? '');
                $uniqueid    = $rec['UNIQUEID']  ?? $rec['uniqueid'] ?? '';

                // Build the stream URL via our Laravel proxy
                // We pass the relative playback_url (e.g. /pbxcore/api/v3/cdr:playback?token=xxx)
                // so our proxy can prepend the MikoPBX base URL and add Bearer auth
                $streamKey = ! empty($playbackUrl) ? $playbackUrl : $filename;
                $proxyUrl  = route('mikopbx.recordings.play', ['filename' => urlencode($streamKey)]);

                // Date display
                $dateDisplay = '';
                if ($date) {
                    try {
                        $dateDisplay = \Carbon\Carbon::parse($date)->format('d M Y H:i');
                    } catch (\Throwable) {
                        $dateDisplay = substr($date, 0, 16);
                    }
                }

                $durationDisplay = $duration > 0 ? gmdate('i:s', $duration) : '—';
                $displayName     = $callerName ?: $caller;
            @endphp

            <div class="px-4 py-3 flex items-center gap-4 hover:bg-gray-50 group"
                 :class="currentFile === '{{ addslashes($streamKey) }}' ? 'bg-indigo-50' : ''">

                {{-- Play/Pause button --}}
                <button @click="play('{{ addslashes($streamKey) }}', '{{ addslashes($displayName) }} → {{ addslashes($callee) }} ({{ $dateDisplay }})')"
                        class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 transition-colors"
                        :class="currentFile === '{{ addslashes($streamKey) }}' && playing
                            ? 'bg-indigo-600 text-white'
                            : 'bg-gray-100 hover:bg-indigo-100 text-gray-600 hover:text-indigo-600'">
                    <template x-if="currentFile === '{{ addslashes($streamKey) }}' && playing">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM7 8a1 1 0 012 0v4a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v4a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                    </template>
                    <template x-if="!(currentFile === '{{ addslashes($streamKey) }}' && playing)">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"/>
                        </svg>
                    </template>
                </button>

                {{-- Call info --}}
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate">
                        {{ $displayName }}
                        @if($callee)
                            <span class="text-gray-400 font-normal text-xs"> → {{ $callee }}</span>
                        @endif
                    </p>
                    <p class="text-xs text-gray-400">
                        {{ $dateDisplay }}
                        @if($durationDisplay !== '—')
                            • {{ $durationDisplay }}
                        @endif
                        @if($filename)
                            • <span class="font-mono">{{ $filename }}</span>
                        @endif
                    </p>
                </div>

                {{-- Simple waveform decoration --}}
                <div class="hidden md:flex items-center gap-px h-8 w-20 flex-shrink-0">
                    @for($i = 0; $i < 20; $i++)
                    @php $h = rand(15, 100); @endphp
                    <div class="flex-1 rounded-full transition-colors"
                         :class="currentFile === '{{ addslashes($streamKey) }}' ? 'bg-indigo-400' : 'bg-gray-200'"
                         style="height:{{ $h }}%"></div>
                    @endfor
                </div>

                {{-- Actions --}}
                <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0">
                    {{-- Download (use download_url from API — has token already) --}}
                    @if($downloadUrl)
                    <a href="{{ rtrim(config('mikopbx.url',''), '/') . $downloadUrl }}"
                       target="_blank"
                       class="btn-secondary text-xs"
                       title="Download">
                        ⬇ DL
                    </a>
                    @endif

                    {{-- Callback button --}}
                    <button onclick="window.mikopbxDial && window.mikopbxDial('{{ $callee ?: $caller }}')"
                            class="btn-success text-xs px-2 py-1"
                            title="Call back">
                        📞
                    </button>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Sticky audio player at bottom --}}
    <div x-show="currentFile"
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="fixed bottom-0 left-60 right-0 bg-white border-t border-gray-200 shadow-2xl z-40">
        <div class="flex items-center gap-4 px-6 py-3 max-w-5xl">
            <div class="flex-1 min-w-0">
                <p class="text-xs font-medium text-gray-900 truncate" x-text="currentLabel"></p>
                <p class="text-xs text-gray-400" x-show="playing">▶ Playing</p>
            </div>
            {{--
                The audio src is set dynamically via recordingPlayer().play()
                We use the proxied Laravel route which adds Bearer auth header
                to the MikoPBX request.
            --}}
            <audio id="recording-player"
                   controls
                   class="flex-1"
                   @play="playing = true"
                   @pause="playing = false"
                   @ended="playing = false">
            </audio>
            <button @click="currentFile = ''; playing = false; $el.previousElementSibling.pause()"
                    class="text-gray-400 hover:text-gray-600 text-lg leading-none flex-shrink-0 p-1">
                ✕
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Override recordingPlayer.play() to use the Laravel proxy URL
// The proxy route handles adding Bearer auth to the MikoPBX request
document.addEventListener('DOMContentLoaded', function () {
    // The recordingPlayer() function already uses the proxyUrl via the route
    // This script patches the src to use the proxy endpoint
    window.addEventListener('mikopbx:play-recording', function (e) {
        const player = document.getElementById('recording-player');
        if (!player) return;
        // Build proxy URL: /pbx/recordings/play?filename=ENCODED_PLAYBACK_URL
        player.src = '/{{ config("mikopbx.route_prefix","pbx") }}/recordings/play?filename=' +
                     encodeURIComponent(e.detail.key);
        player.load();
        player.play().catch(err => console.warn('Audio play error:', err));
    });
});
</script>
@endpush
@endsection
