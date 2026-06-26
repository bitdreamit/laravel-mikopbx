@extends('mikopbx::layouts.app')
@section('title','Campaigns')
@section('heading','Auto Dialer Campaigns')

@section('content')
<div class="space-y-4">

    <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500">{{ $campaigns->total() }} campaigns total</p>
        <a href="{{ route('mikopbx.campaigns.create') }}" class="btn-primary">+ New Campaign</a>
    </div>

    @if($campaigns->isEmpty())
    <div class="bg-white rounded-xl border border-dashed border-gray-200 p-16 text-center">
        <div class="text-4xl mb-3">📢</div>
        <h3 class="text-lg font-semibold text-gray-900">No Campaigns Yet</h3>
        <p class="text-sm text-gray-500 mt-1 mb-6">Create an auto-dialer campaign to call a list of numbers automatically.</p>
        <a href="{{ route('mikopbx.campaigns.create') }}" class="btn-primary">Create First Campaign</a>
    </div>
    @else
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach($campaigns as $campaign)
        @php
            $statusBadge = match($campaign->status) {
                'running'   => 'bg-green-100 text-green-800',
                'paused'    => 'bg-yellow-100 text-yellow-800',
                'completed' => 'bg-blue-100 text-blue-800',
                'failed'    => 'bg-red-100 text-red-800',
                default     => 'bg-gray-100 text-gray-700',
            };
            $barColor = match($campaign->status) {
                'running'   => 'bg-green-500',
                'completed' => 'bg-blue-500',
                default     => 'bg-gray-300',
            };
        @endphp
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow">
            <div class="p-5">
                <div class="flex items-start justify-between mb-3">
                    <div class="min-w-0 flex-1">
                        <h3 class="font-semibold text-gray-900 truncate">{{ $campaign->name }}</h3>
                        <p class="text-xs text-gray-400 mt-0.5">
                            {{ ucfirst($campaign->type) }} •
                            Created {{ $campaign->created_at->diffForHumans() }}
                        </p>
                    </div>
                    <span class="badge ml-2 flex-shrink-0 {{ $statusBadge }}">
                        {{ ucfirst($campaign->status) }}
                    </span>
                </div>

                {{-- Progress bar --}}
                <div class="mb-3">
                    <div class="flex justify-between text-xs text-gray-500 mb-1">
                        <span>{{ $campaign->dialed }}/{{ $campaign->total_numbers }} dialed</span>
                        <span>{{ $campaign->progress }}%</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-700 {{ $barColor }}"
                             style="width:{{ $campaign->progress }}%"></div>
                    </div>
                </div>

                {{-- Stats --}}
                <div class="grid grid-cols-3 gap-2 text-center mb-4">
                    <div class="bg-green-50 rounded-lg p-2">
                        <p class="text-lg font-bold text-green-600">{{ $campaign->answered }}</p>
                        <p class="text-xs text-green-700">Answered</p>
                    </div>
                    <div class="bg-red-50 rounded-lg p-2">
                        <p class="text-lg font-bold text-red-500">{{ $campaign->failed }}</p>
                        <p class="text-xs text-red-600">Failed</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-2">
                        <p class="text-lg font-bold text-gray-700">{{ $campaign->total_numbers - $campaign->dialed }}</p>
                        <p class="text-xs text-gray-500">Pending</p>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex items-center gap-2">
                    <a href="{{ route('mikopbx.campaigns.show', $campaign) }}"
                       class="btn-secondary text-xs flex-1 justify-center">Detail</a>

                    @if(in_array($campaign->status, ['draft','paused']))
                        <form method="POST" action="{{ route('mikopbx.campaigns.start', $campaign) }}" class="flex-1">
                            @csrf
                            <button type="submit" class="btn-success w-full justify-center">▶ Start</button>
                        </form>
                    @elseif($campaign->status === 'running')
                        <form method="POST" action="{{ route('mikopbx.campaigns.pause', $campaign) }}">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center px-3 py-1.5 bg-yellow-100 text-yellow-800 text-xs font-medium rounded-lg hover:bg-yellow-200">
                                ⏸
                            </button>
                        </form>
                        <form method="POST" action="{{ route('mikopbx.campaigns.stop', $campaign) }}"
                              onsubmit="return confirm('Stop campaign?')">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center px-3 py-1.5 bg-red-100 text-red-700 text-xs font-medium rounded-lg hover:bg-red-200">
                                ■
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            @if($campaign->started_at)
            <div class="px-5 py-2 bg-gray-50 border-t border-gray-100 text-xs text-gray-400">
                Started {{ $campaign->started_at->diffForHumans() }}
                @if($campaign->completed_at)
                    • Completed {{ $campaign->completed_at->diffForHumans() }}
                @endif
            </div>
            @endif
        </div>
        @endforeach
    </div>

    <div>{{ $campaigns->links() }}</div>
    @endif
</div>
@endsection
