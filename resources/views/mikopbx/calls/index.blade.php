@extends('mikopbx::layouts.app')
@section('title','Call Logs')
@section('heading','Call Logs')

@section('content')
<div class="space-y-4">
    {{-- Live CallLog Table (Livewire — real-time updates) --}}
    @livewire('mikopbx-call-log-table')
</div>
@endsection
