<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\GoogleService;
use Illuminate\Http\Request;

/** OAuth round-trip started from the client's portal. The project token travels in `state`. */
class GoogleAuthController extends Controller
{
    public function start(Project $project, GoogleService $google)
    {
        if (! GoogleService::configured()) {
            return redirect()->route('portal.show', $project->token)->with('error', __('Google connection is not configured on this server yet.'));
        }

        return redirect()->away($google->authUrl($project));
    }

    public function callback(Request $request, GoogleService $google)
    {
        $project = Project::where('token', (string) $request->query('state'))->firstOrFail();
        if ($request->filled('error')) {
            return redirect()->route('portal.show', $project->token)->with('error', __('Google access was denied.'));
        }
        try {
            $google->connect($project, (string) $request->query('code'));
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('portal.show', $project->token)->with('error', __('Google connection failed.'));
        }

        return redirect()->route('portal.google', $project->token);
    }
}
