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
                <label class="text-xs text-gray-500 block mb-1">Number</label>
                <input type="text" name="number" value="{{ $num }}"
                       placeholder="Filter by caller…" class="input text-sm">
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
                $filename = $rec['recordingfile'] ?? $rec['filename'] ?? $rec['recording'] ?? '';
                $caller   = $rec['src_num']   ?? $rec['caller'] ?? 'Unknown';
                $date     = $rec['start']     ?? $rec['calldate'] ?? $rec['date'] ?? '';
                $duration = (int) ($rec['billsec'] ?? $rec['duration'] ?? 0);
                $isPlaying = false; // server-side placeholder, Alpine handles actual state
            @endphp
            <div class="px-4 py-3 flex items-center gap-4 hover:bg-gray-50 group"
                 :class="currentFile === '{{ $filename }}' ? 'bg-indigo-50' : ''">

                {{-- Play button --}}
                <button @click="play('{{ $filename }}', '{{ addslashes($caller) }} — {{ addslashes($date) }}')"
                        class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 transition-colors"
                        :class="currentFile === '{{ $filename }}' && playing
                            ? 'bg-indigo-600 text-white'
                            : 'bg-gray-100 hover:bg-indigo-100 text-gray-600 hover:text-indigo-600'">
                    {{-- Pause icon when playing, play icon otherwise --}}
                    <template x-if="currentFile === '{{ $filename }}' && playing">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                  d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM7 8a1 1 0 012 0v4a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v4a1 1 0 102 0V8a1 1 0 00-1-1z"
                                  clip-rule="evenodd"/>
                        </svg>
                    </template>
                    <template x-if="!(currentFile === '{{ $filename }}' && playing)">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                  d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z"
                                  clip-rule="evenodd"/>
                        </svg>
                    </template>
                </button>

                {{-- Info --}}
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900">{{ $caller }}</p>
                    <p class="text-xs text-gray-400">
                        {{ $date }} •
                        {{ gmdate('i:s', $duration) }}
                    </p>
                </div>

                {{-- Simple waveform decoration --}}
                <div class="hidden md:flex items-center gap-0.5 h-8 w-24 flex-shrink-0">
                    @for($i = 0; $i < 24; $i++)
                    @php $h = rand(20, 100); @endphp
                    <div class="flex-1 rounded-full transition-colors"
                         :class="currentFile === '{{ $filename }}' ? 'bg-indigo-400' : 'bg-gray-200'"
                         style="height:{{ $h }}%"></div>
                    @endfor
                </div>

                {{-- Download --}}
                @if($filename)
                <a href="{{ route('mikopbx.recordings.play', ['filename' => $filename]) }}"
                   target="_blank"
                   download
                   class="opacity-0 group-hover:opacity-100 btn-secondary text-xs transition-opacity flex-shrink-0">
                    ⬇ Download
                </a>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Sticky audio player --}}
    <div x-show="currentFile"
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="fixed bottom-0 left-60 right-0 bg-white border-t border-gray-200 shadow-2xl px-6 py-4 z-40">
        <div class="flex items-center gap-4 max-w-4xl">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate" x-text="currentLabel"></p>
            </div>
            <audio id="recording-player"
                   controls
                   class="flex-1"
                   @play="playing = true"
                   @pause="playing = false"
                   @ended="playing = false">
            </audio>
            <button @click="currentFile = ''; playing = false"
                    class="text-gray-400 hover:text-gray-600 text-lg leading-none flex-shrink-0">
                ✕
            </button>
        </div>
    </div>
</div>
@endsection
