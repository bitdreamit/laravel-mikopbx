@extends('mikopbx::layouts.app')
@section('title','New Campaign')
@section('heading','Create Campaign')

@section('content')
{{-- campaignCreate() is defined in layouts/app.blade.php head --}}
<div class="max-w-2xl" x-data="campaignCreate()">

    <a href="{{ route('mikopbx.campaigns.index') }}"
       class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-6">
        ← Back
    </a>

    <form method="POST" action="{{ route('mikopbx.campaigns.store') }}"
          enctype="multipart/form-data" class="space-y-6">
        @csrf

        {{-- Basic Info --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
            <h3 class="font-semibold text-gray-900">Campaign Details</h3>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Campaign Name <span class="text-red-500">*</span>
                </label>
                <input name="name" value="{{ old('name') }}"
                       placeholder="e.g. June Promo Calls"
                       class="input" required>
                @error('name')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                    <select name="type" x-model="type" class="input">
                        <option value="agent_connect">Agent Connect</option>
                        <option value="voice_broadcast">Voice Broadcast</option>
                        <option value="survey">Interactive Survey</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Max Simultaneous Calls</label>
                    <input name="max_channels" type="number" min="1" max="20"
                           value="{{ old('max_channels', 5) }}" class="input">
                    <p class="text-xs text-gray-400 mt-1">AMARIP supports up to 5</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Retry Attempts</label>
                    <input name="retry_attempts" type="number" min="0" max="10"
                           value="{{ old('retry_attempts', 3) }}" class="input">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Destination Extension</label>
                    <input name="destination_extension"
                           value="{{ old('destination_extension') }}"
                           placeholder="e.g. 101" class="input">
                    <p class="text-xs text-gray-400 mt-1">For agent_connect type</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Schedule Start (optional)</label>
                <input name="scheduled_at" type="datetime-local"
                       value="{{ old('scheduled_at') }}" class="input">
                <p class="text-xs text-gray-400 mt-1">Leave blank to start manually</p>
            </div>
        </div>

        {{-- Audio (broadcast/survey only) --}}
        <div x-show="type !== 'agent_connect'"
             class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
            <h3 class="font-semibold text-gray-900">Audio Message</h3>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Upload Audio File (MP3/WAV)</label>
                <input type="file" name="audio_file" accept=".mp3,.wav,.ogg" class="input">
                <p class="text-xs text-gray-400 mt-1">Max 10MB. Will be uploaded to MikoPBX sound files.</p>
            </div>
        </div>

        {{-- Number List --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
            <h3 class="font-semibold text-gray-900">Number List</h3>

            <div class="flex gap-3">
                <button type="button" @click="inputMode = 'file'"
                        :class="inputMode === 'file'
                            ? 'bg-indigo-600 text-white'
                            : 'bg-gray-100 text-gray-700'"
                        class="px-4 py-2 text-sm font-medium rounded-lg transition-colors">
                    Upload CSV/TXT
                </button>
                <button type="button" @click="inputMode = 'text'"
                        :class="inputMode === 'text'
                            ? 'bg-indigo-600 text-white'
                            : 'bg-gray-100 text-gray-700'"
                        class="px-4 py-2 text-sm font-medium rounded-lg transition-colors">
                    Paste Numbers
                </button>
            </div>

            <div x-show="inputMode === 'file'">
                <label class="block text-sm font-medium text-gray-700 mb-1">CSV or TXT file</label>
                <input type="file" name="numbers_file" accept=".csv,.txt"
                       class="input" @change="countFile($event)">
                <p class="text-xs text-gray-400 mt-1">One number per line. Optional: number,name format.</p>
                <p class="text-xs text-green-600 mt-1" x-show="fileCount > 0" x-text="fileCount + ' numbers detected'"></p>
                @error('numbers')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div x-show="inputMode === 'text'">
                <label class="block text-sm font-medium text-gray-700 mb-1">Paste numbers (one per line)</label>
                <textarea name="numbers_text" rows="8"
                          placeholder="01711000001&#10;01711000002&#10;01811000003"
                          class="input font-mono text-xs"
                          x-model="numbersText"></textarea>
                <p class="text-xs text-gray-400 mt-1"
                   x-text="numbersText.split('\n').filter(l => l.trim()).length + ' numbers'"></p>
            </div>
        </div>

        {{-- Submit --}}
        <div class="flex items-center gap-3">
            <button type="submit" class="btn-primary px-8">Create Campaign</button>
            <a href="{{ route('mikopbx.campaigns.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
