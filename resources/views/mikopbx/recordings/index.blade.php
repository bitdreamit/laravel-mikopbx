@extends('mikopbx::layouts.app')
@section('title','Recordings')
@section('heading','Call Recordings')

@section('content')
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
                <label class="text-xs text-gray-500 block mb-1">Number</label>
                <input type="text" name="number" value="{{ $num }}" placeholder="Filter by number…" class="input text-sm">
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
            No recordings found for this period.
        </div>
        @else
        <div class="divide-y divide-gray-50">
            @foreach($recordings as $rec)
            @php
                $filename = $rec['filename'] ?? $rec['recording'] ?? null;
                $caller   = $rec['src'] ?? $rec['caller'] ?? 'Unknown';
                $date     = $rec['calldate'] ?? $rec['date'] ?? '';
                $duration = $rec['duration'] ?? $rec['billsec'] ?? 0;
            @endphp
            <div class="px-4 py-3 flex items-center gap-4 hover:bg-gray-50 group"
                 :class="currentFile === '{{ $filename }}' ? 'bg-indigo-50' : ''">

                {{-- Play button --}}
                <button @click="play('{{ $filename }}', '{{ $caller }} — {{ $date }}')"
                        class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 transition-colors"
                        :class="currentFile === '{{ $filename }}' && playing ? 'bg-indigo-600 text-white' : 'bg-gray-100 hover:bg-indigo-100 text-gray-600 hover:text-indigo-600'">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <template x-if="currentFile === '{{ $filename }}' && playing">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM7 8a1 1 0 012 0v4a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v4a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </template>
                        <template x-if="!(currentFile === '{{ $filename }}' && playing)">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"/>
                        </template>
                    </svg>
                </button>

                {{-- Info --}}
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900">{{ $caller }}</p>
                    <p class="text-xs text-gray-400">{{ $date }} • {{ gmdate('i:s', $duration) }}</p>
                </div>

                {{-- Waveform placeholder --}}
                <div class="flex-1 hidden md:flex items-center gap-0.5 h-8">
                    @for($i = 0; $i < 40; $i++)
                    <div class="w-1 rounded-full transition-all"
                         :class="currentFile === '{{ $filename }}' ? 'bg-indigo-400' : 'bg-gray-200'"
                         style="height: {{ rand(20, 100) }}%"></div>
                    @endfor
                </div>

                {{-- Download --}}
                @if($filename)
                <a href="{{ route('mikopbx.recordings.play', ['filename' => $filename]) }}"
                   target="_blank" download
                   class="opacity-0 group-hover:opacity-100 btn-secondary text-xs transition-opacity">
                    ⬇ Download
                </a>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Sticky audio player --}}
    <div x-show="currentFile" x-cloak x-transition
         class="fixed bottom-0 left-60 right-0 bg-white border-t border-gray-200 shadow-2xl px-6 py-4 z-40">
        <div class="flex items-center gap-4 max-w-4xl">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate" x-text="currentLabel"></p>
            </div>
            <audio id="recording-player" controls class="flex-1"
                   @play="playing=true" @pause="playing=false" @ended="playing=false">
            </audio>
            <button @click="currentFile=''; playing=false" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>
    </div>
</div>

<script>
function recordingPlayer() {
    return {
        currentFile:  '',
        currentLabel: '',
        playing:      false,
        play(filename, label) {
            if (this.currentFile === filename) {
                const p = document.getElementById('recording-player');
                this.playing ? p.pause() : p.play();
                return;
            }
            this.currentFile  = filename;
            this.currentLabel = label;
            this.$nextTick(() => {
                const p = document.getElementById('recording-player');
                p.src = `/{{ config('mikopbx.route_prefix','pbx') }}/recordings/play?filename=${encodeURIComponent(filename)}`;
                p.play();
            });
        }
    };
}
</script>
@endsection
