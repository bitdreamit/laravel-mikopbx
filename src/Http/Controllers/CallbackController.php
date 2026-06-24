<?php

namespace BitDreamIT\MikoPBX\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use BitDreamIT\MikoPBX\Models\{Callback, Extension};
use BitDreamIT\MikoPBX\Services\CallbackService;

class CallbackController extends Controller
{
    public function __construct(private CallbackService $svc) {}

    public function index()
    {
        $pending    = Callback::where('status','pending')->orderByDesc('created_at')->paginate(20, ['*'], 'pending_page');
        $completed  = Callback::whereIn('status',['completed','cancelled'])->latest()->paginate(20, ['*'], 'done_page');
        $agents     = Extension::where('active', true)->orderBy('extension')->get();
        return view('mikopbx::callbacks.index', compact('pending','completed','agents'));
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'number'      => 'required|string|max:30',
            'name'        => 'nullable|string|max:100',
            'note'        => 'nullable|string|max:500',
            'priority'    => 'in:low,normal,high,urgent',
            'assigned_to' => 'nullable|exists:'.config('mikopbx.table_prefix','mikopbx_').'extensions,id',
            'scheduled_at'=> 'nullable|date',
        ]);

        $cb = $this->svc->schedule($d['number'], $d);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'id' => $cb->id]);
        }
        return back()->with('success', "Callback scheduled for {$d['number']}.");
    }

    public function attempt(Request $request, Callback $callback)
    {
        $ext = $request->validate(['extension' => 'required|string'])['extension'];
        $ok  = $this->svc->attempt($callback, $ext);
        return response()->json(['success' => $ok]);
    }

    public function cancel(Callback $callback)
    {
        $callback->update(['status' => 'cancelled']);
        return back()->with('success', 'Callback cancelled.');
    }
}
