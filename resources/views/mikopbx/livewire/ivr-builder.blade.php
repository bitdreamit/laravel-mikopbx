<div class="space-y-6">

    {{-- Header bar --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-4 flex items-center gap-4">
        <div class="flex-1">
            <input wire:model="name" placeholder="IVR Name e.g. Main Menu" class="input font-semibold text-base w-64">
        </div>
        <div class="flex items-center gap-3">
            <div>
                <label class="text-xs text-gray-500 mr-2">Timeout</label>
                <select wire:model="timeout" class="input text-sm w-20">
                    @foreach([3,5,7,10] as $t)
                        <option value="{{ $t }}">{{ $t }}s</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500 mr-2">Retries</label>
                <select wire:model="retries" class="input text-sm w-16">
                    @foreach([1,2,3,5] as $r)
                        <option value="{{ $r }}">{{ $r }}</option>
                    @endforeach
                </select>
            </div>
            <button wire:click="save" class="btn-primary">💾 Save to MikoPBX</button>
            <a href="{{ route('mikopbx.ivr.index') }}" class="btn-secondary">View All</a>
        </div>
    </div>

    {{-- Greeting --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <label class="block text-sm font-medium text-gray-700 mb-2">Welcome Greeting Audio (filename in MikoPBX)</label>
        <input wire:model="greeting" placeholder="e.g. greeting.wav or leave blank for TTS"
               class="input w-96">
        <p class="text-xs text-gray-400 mt-1">Upload audio files in MikoPBX Admin → Sound Files first.</p>
    </div>

    {{-- IVR Nodes --}}
    <div class="space-y-4">
        @foreach($nodes as $ni => $node)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            {{-- Node header --}}
            <div class="bg-indigo-50 border-b border-indigo-100 px-5 py-3 flex items-center gap-3">
                <span class="w-7 h-7 bg-indigo-600 text-white rounded-lg flex items-center justify-center text-xs font-bold">
                    {{ $ni + 1 }}
                </span>
                <input wire:model="nodes.{{ $ni }}.label" placeholder="Node label e.g. Main Menu"
                       class="flex-1 bg-transparent font-semibold text-indigo-900 focus:outline-none text-sm">
                <select wire:model="nodes.{{ $ni }}.type" class="text-xs border border-indigo-200 rounded-lg px-2 py-1 bg-white">
                    <option value="greeting">Greeting</option>
                    <option value="menu">Sub Menu</option>
                </select>
                @if(count($nodes) > 1)
                <button wire:click="removeNode({{ $ni }})"
                        class="text-indigo-400 hover:text-red-500 transition-colors text-sm">✕</button>
                @endif
            </div>

            {{-- Audio for this node --}}
            <div class="px-5 py-3 border-b border-gray-100">
                <label class="text-xs text-gray-500">Audio file for this node</label>
                <input wire:model="nodes.{{ $ni }}.audio" placeholder="e.g. submenu.wav"
                       class="input mt-1 text-sm">
            </div>

            {{-- Key mappings --}}
            <div class="p-5">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Key Mappings</p>
                <div class="space-y-2">
                    @foreach($node['keys'] as $ki => $key)
                    <div class="flex items-center gap-3 group">
                        {{-- Digit --}}
                        <div class="w-12 h-10 bg-gray-100 rounded-lg flex items-center justify-center font-bold text-gray-700 flex-shrink-0">
                            <input wire:model="nodes.{{ $ni }}.keys.{{ $ki }}.digit"
                                   placeholder="#" maxlength="1"
                                   class="w-full bg-transparent text-center font-bold focus:outline-none">
                        </div>
                        <span class="text-gray-300 flex-shrink-0">→</span>
                        {{-- Label --}}
                        <input wire:model="nodes.{{ $ni }}.keys.{{ $ki }}.label"
                               placeholder="Label e.g. Sales"
                               class="input text-sm flex-1">
                        {{-- Action --}}
                        <select wire:model="nodes.{{ $ni }}.keys.{{ $ki }}.action" class="input text-sm w-36">
                            <option value="extension">Extension</option>
                            <option value="queue">Queue</option>
                            <option value="ivr">Sub IVR</option>
                            <option value="voicemail">Voicemail</option>
                            <option value="playback">Play Audio</option>
                            <option value="hangup">Hangup</option>
                        </select>
                        {{-- Target --}}
                        <input wire:model="nodes.{{ $ni }}.keys.{{ $ki }}.target"
                               placeholder="Target e.g. 101"
                               class="input text-sm w-24">
                        <button wire:click="removeKey({{ $ni }}, {{ $ki }})"
                                class="opacity-0 group-hover:opacity-100 text-gray-300 hover:text-red-500 transition-all text-sm flex-shrink-0">✕</button>
                    </div>
                    @endforeach
                </div>
                <button wire:click="addKey({{ $ni }})"
                        class="mt-3 text-xs text-indigo-600 hover:text-indigo-800 font-medium flex items-center gap-1">
                    + Add Key
                </button>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Add node --}}
    <button wire:click="addNode"
            class="w-full py-4 border-2 border-dashed border-gray-200 rounded-xl text-sm text-gray-400 hover:border-indigo-300 hover:text-indigo-500 transition-colors">
        + Add Sub Menu Node
    </button>

    {{-- Preview --}}
    <div class="bg-gray-900 rounded-xl p-5 text-green-400 font-mono text-xs leading-relaxed">
        <p class="text-green-600 mb-2">// IVR Flow Preview</p>
        @foreach($nodes as $ni => $node)
        <p><span class="text-yellow-400">[{{ $node['label'] ?? 'Node '.($ni+1) }}]</span></p>
        @foreach(($node['keys'] ?? []) as $key)
        @if($key['digit'])
        <p class="ml-4">Press <span class="text-white font-bold">{{ $key['digit'] }}</span> → <span class="text-blue-400">{{ $key['label'] ?? '' }}</span> ({{ $key['action'] ?? '' }}: {{ $key['target'] ?? '' }})</p>
        @endif
        @endforeach
        @endforeach
    </div>
</div>
