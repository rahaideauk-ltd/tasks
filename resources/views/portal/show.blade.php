<x-layouts.app :title="$project->name" :homeUrl="route('portal.show', $project->token)">
    @php($running = $project->isAnalysing())
    @if ($running)
        <meta http-equiv="refresh" content="20">
    @endif

    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">{{ $project->name }}</h1>
            @if ($project->site_url)<a href="{{ $project->site_url }}" target="_blank" rel="noopener" class="text-sm text-slate-500 hover:underline" dir="ltr">{{ $project->site_url }}</a>@endif
        </div>
        <div class="text-xs text-slate-500 bg-white border border-slate-200 rounded-lg px-3 py-2">
            {{ __('Your private link') }}: <span dir="ltr" class="select-all font-mono">{{ route('portal.show', $project->token) }}</span>
        </div>
    </div>

    @if (session('welcome'))
        <div class="mb-6 rounded-xl bg-slate-900 text-white p-5">
            <div class="font-semibold mb-1">{{ __('Welcome! Your project is created.') }}</div>
            <p class="text-sm text-slate-200">{{ __('We are analysing your website now. Your first tasks will appear here within a few minutes. Bookmark this page.') }}</p>
        </div>
    @endif

    @if ($running)
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 text-amber-900 px-4 py-3 text-sm flex items-center gap-3">
            <span class="inline-block w-3 h-3 rounded-full bg-amber-500 animate-pulse"></span>
            {{ __('Analysing your website and data… this page refreshes automatically.') }}
        </div>
    @endif

    {{-- connections --}}
    <section class="grid md:grid-cols-2 gap-4 mb-8" x-data="{ clarity: false }">
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <h2 class="font-semibold">Google Search Console + Analytics</h2>
                <span class="text-xs px-2 py-0.5 rounded-full {{ $project->hasGoogle() ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $project->hasGoogle() ? __('Connected') : __('Not connected') }}</span>
            </div>
            @if ($project->hasGoogle())
                <dl class="text-sm text-slate-600 space-y-1 mb-3">
                    <div><dt class="inline text-slate-400">Search Console:</dt> <dd class="inline" dir="ltr">{{ $project->gsc_site_url ?? __('not selected') }}</dd></div>
                    <div><dt class="inline text-slate-400">GA4:</dt> <dd class="inline" dir="ltr">{{ $project->ga4_property ?? __('not selected') }}</dd></div>
                </dl>
                <div class="flex gap-2 text-sm">
                    <a href="{{ route('portal.google', $project->token) }}" class="px-3 py-1.5 rounded-lg border border-slate-300 hover:bg-slate-50">{{ __('Change selection') }}</a>
                    <form method="post" action="{{ route('portal.google.disconnect', $project->token) }}">@csrf<button class="px-3 py-1.5 rounded-lg text-red-600 hover:bg-red-50">{{ __('Disconnect') }}</button></form>
                </div>
            @else
                <p class="text-sm text-slate-500 mb-3">{{ __('Lets us see which searches bring visitors and what they do on your site. Read-only access.') }}</p>
                @if ($googleConfigured)
                    <a href="{{ route('portal.google.start', $project->token) }}" class="inline-block bg-slate-900 text-white text-sm px-4 py-2 rounded-lg hover:bg-slate-700">{{ __('Connect Google') }}</a>
                @else
                    <p class="text-xs text-amber-700">{{ __('Google connection is not configured on this server yet.') }}</p>
                @endif
            @endif
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <h2 class="font-semibold">Microsoft Clarity</h2>
                <span class="text-xs px-2 py-0.5 rounded-full {{ $project->hasClarity() ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $project->hasClarity() ? __('Connected') : __('Not connected') }}</span>
            </div>
            <p class="text-sm text-slate-500 mb-3">{{ __('Shows where visitors get stuck (rage clicks, dead clicks, errors). In Clarity: Settings → Data Export → Generate new API token.') }}</p>
            <button type="button" @click="clarity = !clarity" class="text-sm px-3 py-1.5 rounded-lg border border-slate-300 hover:bg-slate-50">{{ $project->hasClarity() ? __('Change token') : __('Add API token') }}</button>
            <form x-show="clarity" x-cloak method="post" action="{{ route('portal.clarity', $project->token) }}" class="mt-3 flex gap-2">
                @csrf
                <input name="api_token" dir="ltr" placeholder="API token" class="flex-1 rounded-lg border-slate-300 text-sm" />
                <button class="bg-slate-900 text-white text-sm px-4 rounded-lg">{{ __('Save') }}</button>
            </form>
        </div>
    </section>

    {{-- tasks --}}
    @if ($tasks->isEmpty())
        <div class="text-center text-slate-500 py-12 bg-white rounded-xl border border-dashed border-slate-300">
            {{ $running ? __('Your tasks are being prepared…') : __('No tasks yet. We will add your first tasks soon.') }}
        </div>
    @endif

    @foreach ($rounds as $round => $roundTasks)
        <section class="mb-8">
            <div class="flex items-center gap-3 mb-3">
                <h2 class="text-lg font-bold">{{ __('Round :n', ['n' => $round]) }}</h2>
                <span class="text-xs text-slate-500">{{ $roundTasks->whereIn('status', ['approved'])->count() }}/{{ $roundTasks->count() }} {{ __('approved') }}</span>
            </div>
            @foreach ($roundTasks->groupBy(fn ($t) => $t->category?->name() ?? __('General')) as $catName => $catTasks)
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mt-4 mb-2">{{ $catName }}</h3>
                <div class="space-y-3">
                    @foreach ($catTasks as $task)
                        <article class="bg-white rounded-xl border border-slate-200 p-4" x-data="{ mode: null }">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h4 class="font-semibold text-slate-900">{{ $task->title() }}</h4>
                                        <x-priority :priority="$task->priority" />
                                        <x-status-badge :status="$task->status" />
                                    </div>
                                    <p class="text-sm text-slate-600 mt-1 whitespace-pre-line">{{ $task->description() }}</p>
                                </div>
                            </div>

                            @if ($task->admin_feedback && in_array($task->status, ['rejected', 'todo', 'approved']))
                                <div class="mt-3 text-sm rounded-lg px-3 py-2 {{ $task->status === 'rejected' ? 'bg-red-50 text-red-800' : 'bg-slate-50 text-slate-700' }}">
                                    <span class="font-medium">{{ __('Our feedback') }}:</span> {{ $task->admin_feedback }}
                                </div>
                            @endif
                            @if ($task->client_note && ! $task->isOpenForClient())
                                <div class="mt-3 text-sm bg-slate-50 text-slate-700 rounded-lg px-3 py-2"><span class="font-medium">{{ __('Your note') }}:</span> {{ $task->client_note }}</div>
                            @endif

                            @if ($task->isOpenForClient())
                                <div class="mt-3 flex gap-2" x-show="!mode">
                                    <button type="button" @click="mode='done'" class="bg-emerald-600 text-white text-sm px-4 py-1.5 rounded-lg hover:bg-emerald-700">{{ __('I did it') }}</button>
                                    <button type="button" @click="mode='notdone'" class="text-sm px-4 py-1.5 rounded-lg border border-slate-300 hover:bg-slate-50">{{ __("I didn't / can't do it") }}</button>
                                </div>
                                <form x-show="mode" x-cloak method="post" action="{{ route('portal.respond', [$project->token, $task]) }}" class="mt-3 space-y-2">
                                    @csrf
                                    <input type="hidden" name="done" :value="mode==='done' ? 1 : 0">
                                    <textarea name="note" rows="2" class="w-full rounded-lg border-slate-300 text-sm" :placeholder="mode==='done' ? @js(__('Optional: a link, screenshot URL or a note about what you did')) : @js(__('Required: tell us why (no time, not possible, need help…)'))" :required="mode==='notdone'"></textarea>
                                    <div class="flex gap-2">
                                        <button class="bg-slate-900 text-white text-sm px-4 py-1.5 rounded-lg">{{ __('Send') }}</button>
                                        <button type="button" @click="mode=null" class="text-sm px-3 py-1.5 text-slate-500">{{ __('Cancel') }}</button>
                                    </div>
                                </form>
                            @elseif (in_array($task->status, ['submitted', 'not_done']))
                                <p class="mt-3 text-xs text-slate-500">{{ __('Waiting for our review.') }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endforeach
        </section>
    @endforeach
</x-layouts.app>
