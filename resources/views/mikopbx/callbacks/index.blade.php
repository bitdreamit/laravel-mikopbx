@extends('mikopbx::layouts.app')
@section('title','Callbacks')
@section('heading','Callback Scheduler')

@section('content')
<div class="space-y-6">

    {{-- Add callback --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-900 text-sm mb-4">Schedule Callback</h3>
        <form method="POST" action="{{ route('mikopbx.callbacks.store') }}"
              class="grid grid-cols-2 md:grid-cols-5 gap-3">
            @csrf
            <input name="number" placeholder="Number *" class="input" required>
            <input name="name" placeholder="Contact name" class="input">
            <select name="priority" class="input">
                <option value="normal">Normal</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
                <option value="low">Low</option>
            </select>
            <input name="scheduled_at" type="datetime-local" class="input"
                   value="{{ now()->addMinutes(15)->format('Y-m-d\TH:i') }}">
            <button type="submit" class="btn-primary justify-center">+ Schedule</button>
        </form>
    </div>

    <div class="grid grid-cols-12 gap-6">

        {{-- Pending (Livewire) --}}
        <div class="col-span-12 lg:col-span-7">
            @livewire('mikopbx-pending-callbacks')
        </div>

        {{-- Completed --}}
        <div class="col-span-12 lg:col-span-5">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="border-b border-gray-100 px-4 py-3">
                    <h3 class="font-semibold text-gray-900 text-sm">Completed / Cancelled</h3>
                </div>
                <div class="divide-y divide-gray-50 max-h-96 overflow-y-auto">
                    @forelse($completed as $cb)
                    @php
                        $cbDot = $cb->status === 'completed' ? 'bg-green-400' : 'bg-gray-300';
                    @endphp
                    <div class="px-4 py-3 flex items-center gap-3">
                        <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $cbDot }}"></span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">
                                {{ $cb->name ?? $cb->number }}
                            </p>
                            <p class="text-xs text-gray-400">
                                {{ $cb->number }} • {{ ucfirst($cb->status) }}
                            </p>
                        </div>
                        <span class="text-xs text-gray-400">{{ $cb->updated_at->diffForHumans() }}</span>
                    </div>
                    @empty
                    <div class="px-4 py-8 text-center text-xs text-gray-400">No completed callbacks</div>
                    @endforelse
                </div>
                @if($completed->hasPages())
                <div class="px-4 py-2 border-t border-gray-100 text-xs">
                    {{ $completed->appends(['pending_page' => request('pending_page')])->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
