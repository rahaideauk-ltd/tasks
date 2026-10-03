<x-layouts.app :title="__('Projects')" :homeUrl="route('admin.projects.index')">
    <x-slot:nav>
        <form method="post" action="{{ route('admin.logout') }}">@csrf<button class="text-slate-500 hover:text-slate-900">{{ __('Log out') }}</button></form>
    </x-slot:nav>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">{{ __('Projects') }}</h1>
        <a href="{{ route('onboarding.create') }}" class="text-sm px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50">{{ __('New project') }}</a>
    </div>

    @if (! $aiConfigured || ! $googleConfigured)
        <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm">
            @unless ($aiConfigured)<div>ANTHROPIC_API_KEY {{ __('is not set: AI task generation is off, only rules run.') }}</div>@endunless
            @unless ($googleConfigured)<div>GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET {{ __('are not set: clients cannot connect Google.') }}</div>@endunless
        </div>
    @endif

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr>
                    <th class="text-start px-4 py-2">{{ __('Project') }}</th>
                    <th class="px-2 py-2">{{ __('To review') }}</th>
                    <th class="px-2 py-2">{{ __('Drafts') }}</th>
                    <th class="px-2 py-2">{{ __('Open') }}</th>
                    <th class="px-2 py-2">{{ __('Approved') }}</th>
                    <th class="px-2 py-2">{{ __('Analysis') }}</th>
                    <th class="px-2 py-2">{{ __('Created') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($projects as $p)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.projects.show', $p) }}" class="font-medium text-slate-900 hover:underline">{{ $p->name }}</a>
                            <div class="text-xs text-slate-500" dir="ltr">{{ $p->site_url }}</div>
                        </td>
                        <td class="text-center px-2 py-3">@if ($p->review_count)<span class="bg-amber-100 text-amber-800 px-2 py-0.5 rounded-full font-medium">{{ $p->review_count }}</span>@else<span class="text-slate-300">0</span>@endif</td>
                        <td class="text-center px-2 py-3">{{ $p->draft_count }}</td>
                        <td class="text-center px-2 py-3">{{ $p->todo_count }}</td>
                        <td class="text-center px-2 py-3">{{ $p->approved_count }}</td>
                        <td class="text-center px-2 py-3 text-xs">{{ __("analysis.{$p->analysis_status}") }}</td>
                        <td class="text-center px-2 py-3 text-xs text-slate-500">{{ $p->created_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">{{ __('No projects yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
