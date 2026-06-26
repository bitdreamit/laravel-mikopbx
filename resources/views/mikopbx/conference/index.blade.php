@extends('mikopbx::layouts.app')
@section('title','Conference')
@section('heading','Conference Rooms')

@section('content')
{{-- conferenceRoom() is defined in layouts/app.blade.php head --}}
<div class="space-y-4">
    @if(empty($rooms))
    <div class="bg-white rounded-xl border border-dashed border-gray-200 p-16 text-center">
        <div class="text-4xl mb-3">🎙️</div>
        <h3 class="text-lg font-semibold text-gray-900">No Conference Rooms</h3>
        <p class="text-sm text-gray-500 mt-1">
            Create conference rooms in MikoPBX Admin → Applications → Conference Rooms.
        </p>
    </div>
    @else
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach($rooms as $room)
        @php
            $roomId = $room['value'] ?? $room['id'] ?? '';
            $roomName = $room['text'] ?? $room['name'] ?? 'Conference Room';
        @endphp
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5"
             x-data="conferenceRoom('{{ $roomId }}')">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h3 class="font-semibold text-gray-900">{{ $roomName }}</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Room #{{ $roomId }}</p>
                </div>
                <span class="badge bg-green-50 text-green-700">
                    <span x-text="participants.length"></span> in room
                </span>
            </div>

            {{-- Participant list --}}
            <div class="space-y-1 mb-4 min-h-12">
                <template x-for="p in participants" :key="p.channel">
                    <div class="flex items-center gap-2 text-xs">
                        <span class="w-2 h-2 bg-green-400 rounded-full pulse-green flex-shrink-0"></span>
                        <span x-text="p.name || p.channel" class="text-gray-700 flex-1 truncate"></span>
                        <button @click="muteP(p.channel)"
                                class="text-gray-400 hover:text-orange-500"
                                title="Mute">🔇</button>
                        <button @click="kick(p.channel)"
                                class="text-gray-400 hover:text-red-500"
                                title="Kick">✕</button>
                    </div>
                </template>
                <template x-if="participants.length === 0">
                    <p class="text-xs text-gray-400 py-2 text-center">Empty room</p>
                </template>
            </div>

            {{-- Dial into room --}}
            <div class="flex gap-2">
                <input x-model="dialIn"
                       placeholder="Dial extension in…"
                       @keydown.enter="addParticipant()"
                       class="input text-xs flex-1">
                <button @click="addParticipant()" class="btn-success text-xs px-3">+ Add</button>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection
