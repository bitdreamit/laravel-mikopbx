<?php

namespace BitDreamIT\MikoPBX\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use BitDreamIT\MikoPBX\Models\Campaign;
use BitDreamIT\MikoPBX\Services\CampaignService;

class CampaignController extends Controller
{
    public function __construct(private CampaignService $svc) {}

    public function index()
    {
        $campaigns = Campaign::latest()->paginate(12);
        return view('mikopbx::campaigns.index', compact('campaigns'));
    }

    public function create()
    {
        return view('mikopbx::campaigns.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                   => 'required|string|max:100',
            'type'                   => 'required|in:agent_connect,voice_broadcast,survey',
            'max_channels'           => 'integer|min:1|max:20',
            'retry_attempts'         => 'integer|min:0|max:10',
            'destination_extension'  => 'nullable|string',
            'scheduled_at'           => 'nullable|date',
            'numbers_file'           => 'nullable|file|mimes:csv,txt',
            'numbers_text'           => 'nullable|string',
        ]);

        // Parse numbers
        $numbers = [];
        if ($request->hasFile('numbers_file')) {
            $numbers = $this->svc->parseNumbersFromFile($request->file('numbers_file'));
        } elseif ($request->numbers_text) {
            foreach (explode("\n", $request->numbers_text) as $line) {
                $n = preg_replace('/\D/', '', trim($line));
                if (strlen($n) >= 7) $numbers[] = ['number' => $n];
            }
        }

        if (empty($numbers)) {
            return back()->withErrors(['numbers' => 'Please provide at least one number.'])->withInput();
        }

        $audio = $request->hasFile('audio_file') ? $request->file('audio_file') : null;
        $campaign = $this->svc->create($data, $numbers, $audio);

        return redirect()->route('mikopbx.campaigns.show', $campaign)
            ->with('success', "Campaign \"{$campaign->name}\" created with " . count($numbers) . ' numbers.');
    }

    public function show(Campaign $campaign)
    {
        $stats   = $this->svc->getStats($campaign);
        $numbers = $campaign->numbers()->latest()->paginate(20);
        return view('mikopbx::campaigns.show', compact('campaign', 'stats', 'numbers'));
    }

    public function start(Campaign $campaign)
    {
        try {
            $this->svc->start($campaign);
            return back()->with('success', 'Campaign started.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function pause(Campaign $campaign)
    {
        $this->svc->pause($campaign);
        return back()->with('success', 'Campaign paused.');
    }

    public function stop(Campaign $campaign)
    {
        $this->svc->stop($campaign);
        return back()->with('success', 'Campaign stopped.');
    }

    public function progress(Campaign $campaign)
    {
        return response()->json($this->svc->getStats($campaign));
    }
}
