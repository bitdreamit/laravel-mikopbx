@extends('mikopbx::layouts.app')
@section('title','IVR Menus')
@section('heading','IVR Menus')

@section('content')
<div class="space-y-4">
    <div class="flex justify-between items-center">
        <p class="text-sm text-gray-500">{{ count($menus) }} IVR menus in MikoPBX</p>
        <a href="{{ route('mikopbx.ivr.builder') }}" class="btn-primary">+ Build New IVR</a>
    </div>

    @if(empty($menus))
    <div class="bg-white rounded-xl border border-dashed border-gray-200 p-16 text-center">
        <div class="text-4xl mb-3">🌿</div>
        <h3 class="text-lg font-semibold text-gray-900">No IVR Menus Yet</h3>
        <p class="text-sm text-gray-500 mt-1 mb-6">Use the visual builder to create your call flow.</p>
        <a href="{{ route('mikopbx.ivr.builder') }}" class="btn-primary">Open IVR Builder</a>
    </div>
    @else
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach($menus as $menu)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="font-semibold text-gray-900">{{ $menu['text'] ?? $menu['name'] ?? 'IVR Menu' }}</h3>
                    <p class="text-xs text-gray-400 mt-0.5">ID: {{ $menu['value'] ?? $menu['id'] ?? '—' }}</p>
                </div>
                <span class="text-2xl">🌿</span>
            </div>
            <div class="mt-4">
                <a href="{{ route('mikopbx.ivr.builder') }}" class="btn-secondary text-xs">Edit</a>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection
