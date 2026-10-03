<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeProject;
use App\Models\Project;
use Illuminate\Http\Request;

/** Public: a business owner creates their project and lands on their portal. */
class OnboardingController extends Controller
{
    public function create()
    {
        return view('onboarding.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'site_url' => ['nullable', 'string', 'max:300'],
            'industry' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'goals' => ['nullable', 'string', 'max:5000'],
            'contact_email' => ['nullable', 'email', 'max:200'],
        ]);
        $data['locale'] = app()->getLocale();

        $project = Project::create($data + ['analysis_status' => 'running']);
        AnalyzeProject::dispatch($project)->afterResponse();

        return redirect()->route('portal.show', $project->token)->with('welcome', true);
    }
}
