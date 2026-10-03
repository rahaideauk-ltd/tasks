<x-layouts.app :title="__('Google settings')" :homeUrl="route('portal.show', $project->token)">
    <div class="max-w-xl mx-auto">
        <h1 class="text-2xl font-bold mb-1">{{ __('Choose your Google properties') }}</h1>
        <p class="text-sm text-slate-500 mb-6">{{ __('Pick the Search Console site and the GA4 property that belong to this website.') }}</p>

        @if ($googleErrors)
            <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm">
                @foreach ($googleErrors as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif

        <form method="post" action="{{ route('portal.google.select', $project->token) }}" class="bg-white rounded-xl border border-slate-200 p-6 space-y-5">
            @csrf
            <div>
                <label class="block text-sm font-medium mb-1">Search Console</label>
                <select name="gsc_site_url" dir="ltr" class="w-full rounded-lg border-slate-300">
                    <option value="">— {{ __('none') }} —</option>
                    @foreach ($sites as $s)
                        <option value="{{ $s['siteUrl'] }}" @selected($project->gsc_site_url === $s['siteUrl'])>{{ $s['siteUrl'] }} ({{ $s['permission'] }})</option>
                    @endforeach
                </select>
                @if (empty($sites))<p class="text-xs text-slate-500 mt-1">{{ __('No sites found on this Google account. Add the site in Search Console first.') }}</p>@endif
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Google Analytics 4</label>
                <select name="ga4_property" dir="ltr" class="w-full rounded-lg border-slate-300">
                    <option value="">— {{ __('none') }} —</option>
                    @foreach ($properties as $p)
                        <option value="{{ $p['property'] }}" @selected($project->ga4_property === $p['property'])>{{ $p['displayName'] }}</option>
                    @endforeach
                </select>
                @if (empty($properties))<p class="text-xs text-slate-500 mt-1">{{ __('No GA4 properties found on this Google account.') }}</p>@endif
            </div>
            <button class="w-full bg-slate-900 text-white py-2.5 rounded-lg font-medium">{{ __('Save') }}</button>
        </form>
    </div>
</x-layouts.app>
