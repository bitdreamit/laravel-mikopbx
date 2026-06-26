@extends('mikopbx::layouts.app')
@section('title', 'Dashboard')
@section('heading', 'Call Center Dashboard')

@section('content')
<div class="space-y-6" x-data="dashboard()">

    {{-- KPI Row --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-4">
        <div class="stat-card">
            <p class="text-xs text-gray-500 font-medium">Today's Calls</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $stats['total_calls'] }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs text-gray-500 font-medium">Answered</p>
            <p class="text-2xl font-bold text-green-600 mt-1">{{ $stats['answered'] }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs text-gray-500 font-medium">Missed</p>
            <p class="text-2xl font-bold text-red-500 mt-1">{{ $stats['missed'] }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs text-gray-500 font-medium">Active Now</p>
            <div class="flex items-center gap-2 mt-1">
                <p class="text-2xl font-bold text-indigo-600" x-text="activeCalls">{{ $stats['active_calls'] }}</p>
                <span class="w-2 h-2 bg-indigo-400 rounded-full pulse-green"></span>
            </div>
        </div>
        <div class="stat-card">
            <p class="text-xs text-gray-500 font-medium">Agents Online</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $stats['agents_online'] }}<span class="text-sm text-gray-400">/{{ $stats['agents_total'] }}</span></p>
        </div>
        <div class="stat-card">
            <p class="text-xs text-gray-500 font-medium">Callbacks</p>
            <p class="text-2xl font-bold text-orange-500 mt-1">{{ $stats['pending_callbacks'] }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs text-gray-500 font-medium">Campaigns</p>
            <p class="text-2xl font-bold text-purple-600 mt-1">{{ $stats['running_campaigns'] }}</p>
        </div>
    </div>

    {{-- Main 3-column grid --}}
    <div class="grid grid-cols-12 gap-6">

        {{-- LEFT: Live Call Board + Agent Grid --}}
        <div class="col-span-12 lg:col-span-5 space-y-4">

            {{-- Live Call Board --}}
            @livewire('mikopbx-live-call-board')

            {{-- Agent Grid --}}
            @livewire('mikopbx-agent-status-grid')
        </div>

        {{-- MIDDLE: Task Manager (htncr.org style) --}}
        <div class="col-span-12 lg:col-span-4" x-data="taskManager()">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="border-b border-gray-100 px-4 py-3 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900 text-sm">Task Manager</h3>
                    <div class="flex gap-1">
                        <button @click="tab='pending'"
                                :class="tab==='pending' ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:text-gray-700'"
                                class="px-2.5 py-1 text-xs font-medium rounded-md transition-colors">
                            Pending <span class="ml-1 opacity-75" x-text="tasks.pending.length"></span>
                        </button>
                        <button @click="tab='done'"
                                :class="tab==='done' ? 'bg-green-600 text-white' : 'text-gray-500 hover:text-gray-700'"
                                class="px-2.5 py-1 text-xs font-medium rounded-md transition-colors">
                            Done
                        </button>
                        <button @click="tab='transferred'"
                                :class="tab==='transferred' ? 'bg-blue-600 text-white' : 'text-gray-500 hover:text-gray-700'"
                                class="px-2.5 py-1 text-xs font-medium rounded-md transition-colors">
                            Transferred
                        </button>
                    </div>
                </div>

                {{-- Add Task --}}
                <div class="px-4 py-3 border-b border-gray-50">
                    <div class="flex gap-2">
                        <input x-model="newTask" @keydown.enter="addTask()"
                               placeholder="Add task or note…"
                               class="flex-1 text-xs border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        <button @click="addTask()"
                                class="px-3 py-2 bg-indigo-600 text-white text-xs rounded-lg hover:bg-indigo-700">+</button>
                    </div>
                </div>

                {{-- Task list --}}
                <div class="divide-y divide-gray-50 max-h-72 overflow-y-auto">
                    <template x-for="(task, i) in tasks[tab]" :key="task.id">
                        <div class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 group">
                            <input type="checkbox" @change="completeTask(tab, i)"
                                   :checked="tab==='done'"
                                   class="mt-0.5 rounded border-gray-300 text-indigo-600 flex-shrink-0">
                            <div class="flex-1 min-w-0">
                                <p class="text-xs text-gray-800 leading-snug" x-text="task.text"
                                   :class="tab==='done' ? 'line-through text-gray-400' : ''"></p>
                                <p class="text-xs text-gray-400 mt-0.5" x-text="task.time"></p>
                            </div>
                            <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0">
                                <button @click="transferTask(tab, i)" title="Transfer" class="text-xs text-blue-500 hover:text-blue-700">↗</button>
                                <button @click="removeTask(tab, i)" class="text-xs text-red-400 hover:text-red-600">✕</button>
                            </div>
                        </div>
                    </template>
                    <div x-show="tasks[tab].length===0" class="px-4 py-8 text-center text-xs text-gray-400">
                        No <span x-text="tab"></span> tasks
                    </div>
                </div>
            </div>
        </div>

        {{-- RIGHT: Follow-up List + Recent Calls --}}
        <div class="col-span-12 lg:col-span-3 space-y-4">

            {{-- Follow-up / Callback List --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="border-b border-gray-100 px-4 py-3 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900 text-sm">Follow-up List</h3>
                    <a href="{{ route('mikopbx.callbacks.index') }}" class="text-xs text-indigo-600 hover:underline">All</a>
                </div>
                <div class="divide-y divide-gray-50 max-h-56 overflow-y-auto">
                    @forelse($pendingCallbacks as $cb)
                        <div class="px-4 py-3 flex items-center gap-3 hover:bg-gray-50 group">
                            <div class="w-8 h-8 rounded-full bg-orange-100 flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-medium text-gray-900 truncate">{{ $cb->name ?? $cb->number }}</p>
                                <p class="text-xs text-gray-400">{{ $cb->number }}</p>
                            </div>
                            <button onclick="window.mikopbxDial && window.mikopbxDial('{{ $cb->number }}')"
                                    class="opacity-0 group-hover:opacity-100 text-green-600 hover:text-green-800 transition-opacity">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
                                </svg>
                            </button>
                        </div>
                    @empty
                        <div class="px-4 py-6 text-center text-xs text-gray-400">No pending follow-ups</div>
                    @endforelse
                </div>
            </div>

            {{-- Recent Calls --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="border-b border-gray-100 px-4 py-3 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900 text-sm">Recent Calls</h3>
                    <a href="{{ route('mikopbx.calls.index') }}" class="text-xs text-indigo-600 hover:underline">All</a>
                </div>
                <div class="divide-y divide-gray-50 max-h-56 overflow-y-auto">
                    @forelse($recentCalls as $call)
                        <div class="px-4 py-2.5 flex items-center gap-3 hover:bg-gray-50">
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0
                                @switch($call->status)
                                    @case('answered') bg-green-400 @break
                                    @case('missed')   bg-red-400 @break
                                    @case('busy')     bg-orange-400 @break
                                    @default          bg-gray-300
                                @endswitch">
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-medium text-gray-900 truncate">{{ $call->caller }}</p>
                                <p class="text-xs text-gray-400">{{ $call->started_at?->diffForHumans() }}</p>
                            </div>
                            <span class="text-xs text-gray-400">{{ $call->duration_formatted }}</span>
                        </div>
                    @empty
                        <div class="px-4 py-6 text-center text-xs text-gray-400">No calls yet today</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Bottom Row: Campaign Manager + Log Call Modal trigger --}}
    <div class="grid grid-cols-12 gap-6">
        <div class="col-span-12 lg:col-span-8">
            @livewire('mikopbx-campaign-manager')
        </div>
        <div class="col-span-12 lg:col-span-4">
            {{-- Quick Actions Panel --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 space-y-3">
                <h3 class="font-semibold text-gray-900 text-sm">Quick Actions</h3>

                <a href="{{ route('mikopbx.campaigns.create') }}"
                   class="flex items-center gap-3 p-3 rounded-lg border border-dashed border-indigo-200 hover:bg-indigo-50 group transition-colors">
                    <span class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center text-indigo-600 group-hover:bg-indigo-200 transition-colors">📢</span>
                    <div>
                        <p class="text-xs font-medium text-gray-900">New Campaign</p>
                        <p class="text-xs text-gray-400">Auto-dial number list</p>
                    </div>
                </a>

                <a href="{{ route('mikopbx.ivr.builder') }}"
                   class="flex items-center gap-3 p-3 rounded-lg border border-dashed border-green-200 hover:bg-green-50 group transition-colors">
                    <span class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center text-green-600 group-hover:bg-green-200 transition-colors">🌿</span>
                    <div>
                        <p class="text-xs font-medium text-gray-900">IVR Builder</p>
                        <p class="text-xs text-gray-400">Design call flow</p>
                    </div>
                </a>

                <a href="{{ route('mikopbx.agents.sync') }}"
                   onclick="event.preventDefault(); fetch(this.href, {method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}'}}).then(()=>location.reload())"
                   class="flex items-center gap-3 p-3 rounded-lg border border-dashed border-gray-200 hover:bg-gray-50 group transition-colors">
                    <span class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-600 group-hover:bg-gray-200 transition-colors">🔄</span>
                    <div>
                        <p class="text-xs font-medium text-gray-900">Sync Extensions</p>
                        <p class="text-xs text-gray-400">Pull from MikoPBX</p>
                    </div>
                </a>

                <a href="{{ route('mikopbx.health.index') }}"
                   class="flex items-center gap-3 p-3 rounded-lg border border-dashed border-gray-200 hover:bg-gray-50 group transition-colors">
                    <span class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-600 group-hover:bg-gray-200 transition-colors">❤️</span>
                    <div>
                        <p class="text-xs font-medium text-gray-900">System Health</p>
                        <p class="text-xs text-gray-400">Check MikoPBX status</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function dashboard() {
    return {
        activeCalls: {{ $stats['active_calls'] }},
        init() {
            setInterval(async () => {
                try {
                    const r = await fetch('{{ route("mikopbx.calls.active") }}');
                    const d = await r.json();
                    this.activeCalls = d.data?.length ?? 0;
                } catch {}
            }, 8000);
        }
    };
}

function taskManager() {
    const stored = JSON.parse(localStorage.getItem('mikopbx_tasks') || '{"pending":[],"done":[],"transferred":[]}');
    return {
        tab: 'pending',
        newTask: '',
        tasks: stored,
        save() { localStorage.setItem('mikopbx_tasks', JSON.stringify(this.tasks)); },
        addTask() {
            if (!this.newTask.trim()) return;
            this.tasks.pending.unshift({ id: Date.now(), text: this.newTask.trim(), time: new Date().toLocaleTimeString() });
            this.newTask = '';
            this.save();
        },
        completeTask(tab, i) {
            const t = this.tasks[tab].splice(i, 1)[0];
            if (tab !== 'done') this.tasks.done.unshift({...t, time: new Date().toLocaleTimeString()});
            this.save();
        },
        transferTask(tab, i) {
            const t = this.tasks[tab].splice(i, 1)[0];
            this.tasks.transferred.unshift({...t, time: new Date().toLocaleTimeString()});
            this.save();
        },
        removeTask(tab, i) { this.tasks[tab].splice(i, 1); this.save(); }
    };
}
</script>
@endpush
@endsection
