@extends('mikopbx::layouts.app')
@section('title','Call Detail')
@section('heading','Call Detail')

@section('content')
<div class="max-w-3xl space-y-6">

    <a href="{{ route('mikopbx.calls.index') }}"
       class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
        ← Back to Calls
    </a>

    {{-- Summary card --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900 font-mono">{{ $call->caller }}</h2>
                <p class="text-sm text-gray-500 mt-1">
                    {{ ucfirst($call->direction) }} •
                    {{ $call->started_at?->format('d M Y, H:i:s') }}
                </p>
            </div>
            @php
                $statusBadge = match($call->status) {
                    'answered' => 'bg-green-100 text-green-800',
                    'missed'   => 'bg-red-100 text-red-800',
                    'busy'     => 'bg-orange-100 text-orange-800',
                    'failed'   => 'bg-red-200 text-red-900',
                    default    => 'bg-gray-100 text-gray-700',
                };
            @endphp
            <span class="badge text-sm px-3 py-1 {{ $statusBadge }}">
                {{ ucfirst($call->status) }}
            </span>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6 pt-6 border-t border-gray-100">
            <div>
                <p class="text-xs text-gray-400">Extension</p>
                <p class="text-sm font-semibold text-gray-900">{{ $call->extension ?: '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400">Duration</p>
                <p class="text-sm font-semibold text-gray-900">{{ $call->duration_formatted }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400">Answered At</p>
                <p class="text-sm font-semibold text-gray-900">{{ $call->answered_at?->format('H:i:s') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400">Ended At</p>
                <p class="text-sm font-semibold text-gray-900">{{ $call->ended_at?->format('H:i:s') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400">Direction</p>
                <p class="text-sm font-semibold text-gray-900">{{ ucfirst($call->direction) }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400">Channel</p>
                <p class="text-sm font-mono text-gray-700 truncate" title="{{ $call->channel }}">{{ $call->channel ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400">Unique ID</p>
                <p class="text-sm font-mono text-gray-700 truncate" title="{{ $call->uniqueid }}">{{ $call->uniqueid ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400">Cause</p>
                <p class="text-sm text-gray-700">{{ $call->cause ?? 'Normal' }}</p>
            </div>
        </div>
    </div>

    {{-- Recording --}}
    @if($call->recording_file || $call->recording_url)
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-semibold text-gray-900 mb-4">Call Recording</h3>
        <audio controls class="w-full"
               src="{{ route('mikopbx.recordings.play', ['filename' => $call->recording_file]) }}">
            Your browser does not support audio playback.
        </audio>
        <p class="text-xs text-gray-400 mt-2">{{ $call->recording_file }}</p>
    </div>
    @endif

    {{-- Actions --}}
    <div class="flex items-center gap-3 flex-wrap">
        <button onclick="window.mikopbxDial && window.mikopbxDial('{{ $call->caller }}')"
                class="btn-primary">
            📞 Call Back {{ $call->caller }}
        </button>

        <form method="POST" action="{{ route('mikopbx.callbacks.store') }}">
            @csrf
            <input type="hidden" name="number" value="{{ $call->caller }}">
            <input type="hidden" name="note" value="Follow-up from call #{{ $call->id }}">
            <input type="hidden" name="call_log_id" value="{{ $call->id }}">
            <button type="submit" class="btn-secondary">+ Schedule Callback</button>
        </form>

        <form method="POST" action="{{ route('mikopbx.blacklist.store') }}"
              onsubmit="return confirm('Block {{ $call->caller }}?')">
            @csrf
            <input type="hidden" name="number" value="{{ $call->caller }}">
            <input type="hidden" name="reason" value="Blocked from call log">
            <button type="submit" class="btn-danger">🚫 Blacklist</button>
        </form>
    </div>
</div>
@endsection
