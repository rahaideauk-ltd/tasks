<x-layouts.app :title="$project->name" :homeUrl="route('admin.projects.index')">
    <x-slot:nav>
        <a href="{{ route('admin.projects.index') }}" class="text-slate-500 hover:text-slate-900">{{ __('Projects') }}</a>
        <form method="post" action="{{ route('admin.logout') }}">@csrf<button class="text-slate-500 hover:text-slate-900">{{ __('Log out') }}</button></form>
    </x-slot:nav>
    @php($running = $project->analysis_status === 'running')
    @if ($running)<meta http-equiv="refresh" content="15">@endif

    {{-- header --}}
    <div class="flex flex-wrap items-start justify-between gap-4 mb-4" x-data="{ edit: false }">
        <div>
            <h1 class="text-2xl font-bold">{{ $project->name }}</h1>
            <div class="text-sm text-slate-500" dir="ltr">{{ $project->site_url }}</div>
            <div class="text-xs text-slate-500 mt-1">{{ __('Client link') }}: <a href="{{ route('portal.show', $project->token) }}" target="_blank" class="font-mono select-all hover:underline" dir="ltr">{{ route('portal.show', $project->token) }}</a></div>
            <button type="button" @click="edit = !edit" class="text-xs text-slate-500 underline mt-1">{{ __('Edit profile') }}</button>
        </div>
        <div class="flex flex-wrap gap-2 text-sm">
            <form method="post" action="{{ route('admin.projects.sync', $project) }}">@csrf<button class="px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50">{{ __('Refresh data') }}</button></form>
            <form method="post" action="{{ route('admin.projects.analyze', $project) }}">@csrf<button class="px-3 py-1.5 rounded-lg bg-slate-900 text-white hover:bg-slate-700" @disabled($running)>{{ $running ? __('Analysing…') : __('Analyse & suggest tasks') }}</button></form>
            <form method="post" action="{{ route('admin.projects.destroy', $project) }}" onsubmit="return confirm('{{ __('Delete this project and all its tasks?') }}')">@csrf @method('delete')<button class="px-3 py-1.5 rounded-lg text-red-600 hover:bg-red-50">{{ __('Delete') }}</button></form>
        </div>

        <form x-show="edit" x-cloak method="post" action="{{ route('admin.projects.update', $project) }}" class="w-full bg-white rounded-xl border border-slate-200 p-4 grid md:grid-cols-2 gap-3 text-sm">
            @csrf @method('put')
            <label>{{ __('Business name') }}<input name="name" value="{{ $project->name }}" required class="w-full rounded-lg border-slate-300 mt-1"></label>
            <label>{{ __('Website address') }}<input name="site_url" value="{{ $project->site_url }}" dir="ltr" class="w-full rounded-lg border-slate-300 mt-1"></label>
            <label>{{ __('Industry / what you sell') }}<input name="industry" value="{{ $project->industry }}" class="w-full rounded-lg border-slate-300 mt-1"></label>
            <label>{{ __('Email (optional)') }}<input name="contact_email" value="{{ $project->contact_email }}" dir="ltr" class="w-full rounded-lg border-slate-300 mt-1"></label>
            <label class="md:col-span-2">{{ __('Describe your business') }}<textarea name="description" rows="3" class="w-full rounded-lg border-slate-300 mt-1">{{ $project->description }}</textarea></label>
            <label class="md:col-span-2">{{ __('Goals') }}<textarea name="goals" rows="2" class="w-full rounded-lg border-slate-300 mt-1">{{ $project->goals }}</textarea></label>
            <div class="md:col-span-2"><button class="px-4 py-1.5 rounded-lg bg-slate-900 text-white">{{ __('Save') }}</button></div>
        </form>
    </div>

    {{-- profile + data snapshot --}}
    @php($d = $project->data ?? [])
    <section class="grid md:grid-cols-3 gap-4 mb-6 text-sm">
        <div class="bg-white rounded-xl border border-slate-200 p-4 md:col-span-1">
            <h2 class="font-semibold mb-2">{{ __('Business profile') }}</h2>
            <dl class="space-y-1 text-slate-600">
                @if ($project->industry)<div><dt class="text-slate-400 text-xs">{{ __('Industry / what you sell') }}</dt><dd>{{ $project->industry }}</dd></div>@endif
                @if ($project->description)<div><dt class="text-slate-400 text-xs">{{ __('Describe your business') }}</dt><dd class="whitespace-pre-line">{{ $project->description }}</dd></div>@endif
                @if ($project->goals)<div><dt class="text-slate-400 text-xs">{{ __('Goals') }}</dt><dd class="whitespace-pre-line">{{ $project->goals }}</dd></div>@endif
                @if ($project->contact_email)<div><dt class="text-slate-400 text-xs">{{ __('Email (optional)') }}</dt><dd dir="ltr">{{ $project->contact_email }}</dd></div>@endif
            </dl>
            @if ($project->analysis['summary'][app()->getLocale()] ?? null)
                <h3 class="font-semibold mt-4 mb-1">{{ __('AI assessment') }}</h3>
                <p class="text-slate-600 whitespace-pre-line">{{ $project->analysis['summary'][app()->getLocale()] }}</p>
            @endif
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-4 md:col-span-2">
            <div class="flex items-center justify-between mb-2">
                <h2 class="font-semibold">{{ __('Data snapshot') }}</h2>
                <span class="text-xs text-slate-500">{{ $project->data_fetched_at ? $project->data_fetched_at->diffForHumans() : __('never fetched') }}</span>
            </div>
            <div class="grid sm:grid-cols-2 gap-3">
                <div class="rounded-lg bg-slate-50 p-3">
                    <div class="text-xs text-slate-500 mb-1">{{ __('Website') }}</div>
                    @if (!empty($d['site']['error']))<div class="text-red-600 text-xs">{{ $d['site']['error'] }}</div>
                    @elseif (!empty($d['site']))
                        <div class="truncate" title="{{ $d['site']['title'] ?? '' }}">{{ $d['site']['title'] ?: '—' }}</div>
                        <div class="text-xs text-slate-500 mt-1 flex flex-wrap gap-2">
                            <span>HTTP {{ $d['site']['status'] ?? '?' }}</span>
                            <span class="{{ ($d['site']['https'] ?? false) ? 'text-emerald-600' : 'text-red-600' }}">HTTPS</span>
                            <span class="{{ ($d['site']['hasViewport'] ?? false) ? 'text-emerald-600' : 'text-red-600' }}">viewport</span>
                            <span class="{{ ($d['site']['hasGa'] ?? false) ? 'text-emerald-600' : 'text-slate-400' }}">GA tag</span>
                            <span class="{{ ($d['site']['hasClarity'] ?? false) ? 'text-emerald-600' : 'text-slate-400' }}">Clarity tag</span>
                            <span class="{{ ($d['site']['metaDesc'] ?? '') ? 'text-emerald-600' : 'text-red-600' }}">meta desc</span>
                        </div>
                    @else <div class="text-slate-400">—</div>@endif
                </div>
                <div class="rounded-lg bg-slate-50 p-3">
                    <div class="text-xs text-slate-500 mb-1">Search Console <span class="text-slate-400">({{ $project->gsc_site_url ?: __('not connected') }})</span></div>
                    @if (!empty($d['errors']['gsc']))<div class="text-red-600 text-xs">{{ $d['errors']['gsc'] }}</div>
                    @elseif (!empty($d['gsc']))
                        @php($t = $d['gsc']['totals'])
                        <div class="grid grid-cols-4 gap-1 text-center">
                            <div><div class="font-semibold">{{ number_format($t['clicks']) }}</div><div class="text-[10px] text-slate-500">clicks</div></div>
                            <div><div class="font-semibold">{{ number_format($t['impressions']) }}</div><div class="text-[10px] text-slate-500">impr.</div></div>
                            <div><div class="font-semibold">{{ $t['ctr'] }}%</div><div class="text-[10px] text-slate-500">CTR</div></div>
                            <div><div class="font-semibold">{{ $t['position'] }}</div><div class="text-[10px] text-slate-500">pos.</div></div>
                        </div>
                        <div class="text-[10px] text-slate-400 mt-1">{{ $d['gsc']['range']['startDate'] }} → {{ $d['gsc']['range']['endDate'] }}</div>
                    @else <div class="text-slate-400">—</div>@endif
                </div>
                <div class="rounded-lg bg-slate-50 p-3">
                    <div class="text-xs text-slate-500 mb-1">GA4 <span class="text-slate-400">({{ $project->ga4_property ?: __('not connected') }})</span></div>
                    @if (!empty($d['errors']['ga4']))<div class="text-red-600 text-xs">{{ $d['errors']['ga4'] }}</div>
                    @elseif (!empty($d['ga4']))
                        @php($t = $d['ga4']['totals'])
                        <div class="grid grid-cols-4 gap-1 text-center">
                            <div><div class="font-semibold">{{ number_format($t['sessions']) }}</div><div class="text-[10px] text-slate-500">sessions</div></div>
                            <div><div class="font-semibold">{{ number_format($t['users']) }}</div><div class="text-[10px] text-slate-500">users</div></div>
                            <div><div class="font-semibold">{{ $t['engagementRate'] }}%</div><div class="text-[10px] text-slate-500">engaged</div></div>
                            <div><div class="font-semibold">{{ $t['conversions'] }}</div><div class="text-[10px] text-slate-500">conv.</div></div>
                        </div>
                    @else <div class="text-slate-400">—</div>@endif
                </div>
                <div class="rounded-lg bg-slate-50 p-3">
                    <div class="text-xs text-slate-500 mb-1">Clarity <span class="text-slate-400">({{ $project->hasClarity() ? __('Connected') : __('not connected') }})</span></div>
                    @if (!empty($d['errors']['clarity']))<div class="text-red-600 text-xs">{{ $d['errors']['clarity'] }}</div>
                    @elseif (!empty($d['clarity']))
                        @php($t = $d['clarity']['totals'])
                        <div class="grid grid-cols-4 gap-1 text-center">
                            <div><div class="font-semibold">{{ number_format($t['sessions']) }}</div><div class="text-[10px] text-slate-500">sessions</div></div>
                            <div><div class="font-semibold">{{ round($t['rageClicks'], 1) }}%</div><div class="text-[10px] text-slate-500">rage</div></div>
                            <div><div class="font-semibold">{{ round($t['deadClicks'], 1) }}%</div><div class="text-[10px] text-slate-500">dead</div></div>
                            <div><div class="font-semibold">{{ round($t['jsErrors'], 1) }}%</div><div class="text-[10px] text-slate-500">js err</div></div>
                        </div>
                    @else <div class="text-slate-400">—</div>@endif
                </div>
            </div>
            @if (!empty($project->analysis['errors']))
                <div class="mt-3 text-xs text-red-600">@foreach ($project->analysis['errors'] as $k => $e)<div>{{ $k }}: {{ $e }}</div>@endforeach</div>
            @endif
        </div>
    </section>

    {{-- review queue --}}
    @if ($review->isNotEmpty())
        <section class="mb-8">
            <h2 class="text-lg font-bold mb-3">{{ __('Needs review') }} <span class="text-sm font-normal text-slate-500">({{ $review->count() }})</span></h2>
            <div class="space-y-3">
                @foreach ($review as $task)
                    <article class="bg-white rounded-xl border-2 border-amber-200 p-4" x-data="{ reject: false }">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-xs text-slate-400">R{{ $task->round }} · {{ $task->category?->name() }}</span>
                            <h3 class="font-semibold">{{ $task->title() }}</h3>
                            <x-status-badge :status="$task->status" />
                        </div>
                        <div class="mt-2 text-sm rounded-lg px-3 py-2 {{ $task->status === 'not_done' ? 'bg-orange-50 text-orange-900' : 'bg-slate-50 text-slate-700' }}">
                            <span class="font-medium">{{ $task->status === 'not_done' ? __('Client reason') : __('Client note') }}:</span> {{ $task->client_note ?: '—' }}
                            <span class="text-xs text-slate-400">· {{ $task->responded_at?->diffForHumans() }}</span>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2 text-sm" x-show="!reject">
                            @if ($task->status === 'submitted')
                                <form method="post" action="{{ route('admin.tasks.review', $task) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">{{ __('Approve') }}</button></form>
                                <button type="button" @click="reject = true" class="px-3 py-1.5 rounded-lg border border-red-300 text-red-700 hover:bg-red-50">{{ __('Send back with feedback') }}</button>
                            @else
                                <button type="button" @click="reject = true" class="px-3 py-1.5 rounded-lg bg-slate-900 text-white">{{ __('Reopen with guidance') }}</button>
                                <form method="post" action="{{ route('admin.tasks.review', $task) }}">@csrf<input type="hidden" name="decision" value="drop"><button class="px-3 py-1.5 rounded-lg border border-slate-300 hover:bg-slate-50">{{ __('Drop task') }}</button></form>
                            @endif
                        </div>
                        <form x-show="reject" x-cloak method="post" action="{{ route('admin.tasks.review', $task) }}" class="mt-3 space-y-2 text-sm">
                            @csrf
                            <input type="hidden" name="decision" value="{{ $task->status === 'submitted' ? 'reject' : 'reopen' }}">
                            <textarea name="feedback" rows="2" required class="w-full rounded-lg border-slate-300" placeholder="{{ __('Feedback for the client') }}"></textarea>
                            <div class="flex gap-2"><button class="px-3 py-1.5 rounded-lg bg-slate-900 text-white">{{ __('Send') }}</button><button type="button" @click="reject=false" class="px-3 py-1.5 text-slate-500">{{ __('Cancel') }}</button></div>
                        </form>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    {{-- drafts --}}
    <section class="mb-8" x-data="{ add: false }">
        <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
            <h2 class="text-lg font-bold">{{ __('Drafts (next round)') }} <span class="text-sm font-normal text-slate-500">({{ $drafts->count() }})</span></h2>
            <div class="flex gap-2 text-sm">
                <button type="button" @click="add = !add" class="px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50">{{ __('Add task manually') }}</button>
                @if ($drafts->isNotEmpty())
                    <form method="post" action="{{ route('admin.projects.publish', $project) }}">@csrf<button class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">{{ __('Publish all as round :n', ['n' => $project->currentRound() + 1]) }}</button></form>
                @endif
            </div>
        </div>
        @if ($autoPublish)<p class="text-xs text-slate-500 mb-2">{{ __('Auto-publish is on: generated tasks go straight to the client.') }}</p>@endif

        <form x-show="add" x-cloak method="post" action="{{ route('admin.tasks.store', $project) }}" class="bg-white rounded-xl border border-slate-200 p-4 grid md:grid-cols-2 gap-3 text-sm mb-4">
            @csrf
            <label>{{ __('Title (fa)') }}<input name="title_fa" class="w-full rounded-lg border-slate-300 mt-1"></label>
            <label>{{ __('Title (en)') }}<input name="title_en" dir="ltr" class="w-full rounded-lg border-slate-300 mt-1"></label>
            <label>{{ __('Description (fa)') }}<textarea name="description_fa" rows="3" class="w-full rounded-lg border-slate-300 mt-1"></textarea></label>
            <label>{{ __('Description (en)') }}<textarea name="description_en" rows="3" dir="ltr" class="w-full rounded-lg border-slate-300 mt-1"></textarea></label>
            <label>{{ __('Category') }}<input name="category" list="cats" class="w-full rounded-lg border-slate-300 mt-1"><datalist id="cats">@foreach ($categories as $c)<option value="{{ $c->name_en }}">{{ $c->name_fa }}</option>@endforeach</datalist></label>
            <label>{{ __('Priority') }}<select name="priority" class="w-full rounded-lg border-slate-300 mt-1"><option value="high">{{ __('priority.high') }}</option><option value="medium" selected>{{ __('priority.medium') }}</option><option value="low">{{ __('priority.low') }}</option></select></label>
            <div class="md:col-span-2 flex gap-3 items-center">
                <button name="draft" value="1" class="px-4 py-1.5 rounded-lg bg-slate-900 text-white">{{ __('Add to drafts') }}</button>
                <button name="draft" value="0" class="px-4 py-1.5 rounded-lg border border-slate-300">{{ __('Add to current round now') }}</button>
            </div>
        </form>

        @if ($drafts->isEmpty())
            <div class="text-sm text-slate-500 bg-white rounded-xl border border-dashed border-slate-300 p-6 text-center">{{ $running ? __('Generating suggestions…') : __('No drafts. Click "Analyse & suggest tasks" or add one manually.') }}</div>
        @else
            @foreach ($drafts->groupBy(fn ($t) => $t->category?->name() ?? __('General')) as $catName => $catTasks)
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mt-3 mb-2">{{ $catName }}</h3>
                <div class="space-y-2">
                    @foreach ($catTasks as $task)
                        @include('admin.projects._task', ['task' => $task, 'categories' => $categories])
                    @endforeach
                </div>
            @endforeach
        @endif
    </section>

    {{-- published rounds --}}
    @foreach ($rounds as $round => $roundTasks)
        <section class="mb-8">
            <h2 class="text-lg font-bold mb-3">{{ __('Round :n', ['n' => $round]) }} <span class="text-sm font-normal text-slate-500">({{ $roundTasks->where('status', 'approved')->count() }}/{{ $roundTasks->count() }} {{ __('approved') }})</span></h2>
            @foreach ($roundTasks->groupBy(fn ($t) => $t->category?->name() ?? __('General')) as $catName => $catTasks)
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mt-3 mb-2">{{ $catName }}</h3>
                <div class="space-y-2">
                    @foreach ($catTasks as $task)
                        @include('admin.projects._task', ['task' => $task, 'categories' => $categories])
                    @endforeach
                </div>
            @endforeach
        </section>
    @endforeach
</x-layouts.app>
