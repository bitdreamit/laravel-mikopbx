<?php

namespace BitDreamIT\MikoPBX\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use BitDreamIT\MikoPBX\Models\Extension;
use BitDreamIT\MikoPBX\Services\WebDialerService;

class WebDialerController extends Controller
{
    public function __construct(private WebDialerService $svc) {}

    /**
     * Returns SIP config for the current user's assigned extension.
     * Called by the JS softphone on page load.
     */
    public function config(Request $request)
    {
        if (! $this->svc->isEnabled()) {
            return response()->json(['enabled' => false]);
        }

        // Find the extension linked to this user
        // Assumes Extension has a user_id or the user passes their extension
        $extension = $request->extension
            ?? Extension::where('email', auth()->user()?->email)->value('extension')
            ?? null;

        $password = $request->password ?? '';

        if (! $extension) {
            return response()->json(['enabled' => true, 'extension' => null, 'error' => 'No extension assigned']);
        }

        return response()->json(array_merge(
            ['enabled' => true],
            $this->svc->getConfig($extension, $password)
        ));
    }
}
