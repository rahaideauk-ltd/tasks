{{-- Internal knowledge: facts, brief, memory. Admin-only, never rendered on the portal. --}}
@php($suggested = $facts->where('status', 'suggested')->count())
<section class="mb-8 bg-white rounded-xl border border-slate-200 p-4 text-sm" x-data="{ tab: 'facts' }">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <h2 class="font-semibold">{{ __('Project knowledge') }} <span class="text-xs font-normal text-slate-400">· {{ __('internal, the client never sees this') }}</span></h2>
        <div class="flex gap-1 text-xs">
            <button type="button" @click="tab = 'facts'" :class="tab === 'facts' ? 'bg-slate-900 text-white' : 'border border-slate-300 hover:bg-slate-50'" class="px-3 py-1 rounded-lg">
                {{ __('Facts') }} ({{ $facts->where('status', '!=', 'rejected')->count() }})@if ($suggested)<span class="ms-1 bg-amber-400 text-amber-950 rounded-full px-1.5">{{ $suggested }}</span>@endif
            </button>
            <button type="button" @click="tab = 'brief'" :class="tab === 'brief' ? 'bg-slate-900 text-white' : 'border border-slate-300 hover:bg-slate-50'" class="px-3 py-1 rounded-lg">{{ __('Our brief') }}</button>
            <button type="button" @click="tab = 'memory'" :class="tab === 'memory' ? 'bg-slate-900 text-white' : 'border border-slate-300 hover:bg-slate-50'" class="px-3 py-1 rounded-lg">{{ __('AI memory') }}</button>
        </div>
    </div>

    {{-- facts --}}
    <div x-show="tab === 'facts'">
        @if ($facts->isEmpty())
            <p class="text-slate-500 mb-3">{{ __('No facts yet. Add keywords, competitors or features, or run an analysis and Claude will suggest some.') }}</p>
        @endif
        <div class="grid md:grid-cols-2 gap-3">
            @foreach ($facts->groupBy('kind') as $kind => $items)
                <div class="rounded-lg bg-slate-50 p-3">
                    <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">{{ \App\Models\ProjectFact::kindLabel($kind) }}</h3>
                    <ul class="space-y-1.5">
                        @foreach ($items as $fact)
                            <li class="flex items-start justify-between gap-2 {{ $fact->status === 'rejected' ? 'opacity-50' : '' }}" x-data="{ edit: false }">
                                <div class="min-w-0" x-show="!edit">
                                    <span class="font-medium {{ $fact->status === 'rejected' ? 'line-through' : '' }}">{{ $fact->value }}</span>
                                    @if ($fact->status === 'suggested')<span class="text-[10px] bg-amber-100 text-amber-800 rounded px-1">{{ __('suggested by AI') }}</span>@endif
                                    @if ($fact->note)<div class="text-xs text-slate-500">{{ $fact->note }}</div>@endif
                                </div>
                                <form x-show="edit" x-cloak method="post" action="{{ route('admin.facts.update', $fact) }}" class="flex-1 space-y-1">
                                    @csrf @method('put')
                                    <input name="value" value="{{ $fact->value }}" class="w-full rounded border-slate-300 text-sm py-1">
                                    <input name="note" value="{{ $fact->note }}" class="w-full rounded border-slate-300 text-xs py-1" placeholder="{{ __('Note') }}">
                                    <div class="flex gap-2 text-xs"><button class="px-2 py-0.5 rounded bg-slate-900 text-white">{{ __('Save') }}</button><button type="button" @click="edit = false" class="text-slate-500">{{ __('Cancel') }}</button></div>
                                </form>
                                <div class="flex gap-1 shrink-0 text-xs" x-show="!edit">
                                    @if ($fact->status !== 'confirmed')
                                        <form method="post" action="{{ route('admin.facts.update', $fact) }}">@csrf @method('put')<input type="hidden" name="status" value="confirmed"><button class="px-1.5 py-0.5 rounded border border-emerald-300 text-emerald-700 hover:bg-emerald-50" title="{{ __('Confirm') }}">✓</button></form>
                                    @endif
                                    @if ($fact->status !== 'rejected')
                                        <form method="post" action="{{ route('admin.facts.update', $fact) }}">@csrf @method('put')<input type="hidden" name="status" value="rejected"><button class="px-1.5 py-0.5 rounded border border-slate-300 hover:bg-white" title="{{ __('Reject (AI will not suggest it again)') }}">⊘</button></form>
                                    @endif
                                    <button type="button" @click="edit = true" class="px-1.5 py-0.5 rounded border border-slate-300 hover:bg-white">✎</button>
                                    <form method="post" action="{{ route('admin.facts.destroy', $fact) }}" onsubmit="return confirm('{{ __('Delete this fact?') }}')">@csrf @method('delete')<button class="px-1.5 py-0.5 rounded border border-slate-300 text-red-600 hover:bg-red-50">✕</button></form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        <form method="post" action="{{ route('admin.facts.store', $project) }}" class="mt-3 grid sm:grid-cols-[10rem_1fr_1fr_auto] gap-2 items-end">
            @csrf
            <label class="text-xs text-slate-500">{{ __('Type') }}
                <input name="kind" list="fact-kinds" required class="w-full rounded-lg border-slate-300 mt-1 text-sm" placeholder="keyword">
                <datalist id="fact-kinds">@foreach (collect($factKinds)->merge($facts->pluck('kind'))->unique() as $k)<option value="{{ $k }}">{{ \App\Models\ProjectFact::kindLabel($k) }}</option>@endforeach</datalist>
            </label>
            <label class="text-xs text-slate-500">{{ __('Value') }}<input name="value" required class="w-full rounded-lg border-slate-300 mt-1 text-sm"></label>
            <label class="text-xs text-slate-500">{{ __('Note') }}<input name="note" class="w-full rounded-lg border-slate-300 mt-1 text-sm"></label>
            <button class="px-3 py-2 rounded-lg bg-slate-900 text-white">{{ __('Add') }}</button>
        </form>
    </div>

    {{-- brief: written by us, Claude only reads it --}}
    <form x-show="tab === 'brief'" x-cloak method="post" action="{{ route('admin.projects.brief', $project) }}" class="space-y-2">
        @csrf @method('put')
        <p class="text-xs text-slate-500">{{ __('Our own notes about this project: context, rules, what to avoid. Claude reads this before every analysis and never changes it.') }}</p>
        <textarea name="brief" rows="12" class="w-full rounded-lg border-slate-300 font-mono text-sm" placeholder="{{ __('e.g. The owner has no developer; avoid code tasks. Main competitor is X. Focus on Tehran.') }}">{{ $project->brief }}</textarea>
        <button class="px-4 py-1.5 rounded-lg bg-slate-900 text-white">{{ __('Save') }}</button>
    </form>

    {{-- memory: Claude rewrites it after each analysis; admin can edit or restore --}}
    <div x-show="tab === 'memory'" x-cloak class="space-y-3">
        <form method="post" action="{{ route('admin.projects.memory', $project) }}" class="space-y-2">
            @csrf @method('put')
            <p class="text-xs text-slate-500">
                {{ __('Claude keeps this up to date after every analysis: what it learned about the business, what worked and what did not. You can correct it; every version is kept.') }}
                @if ($project->memory_updated_at)<span class="text-slate-400">· {{ __('Updated') }} {{ $project->memory_updated_at->diffForHumans() }}</span>@endif
            </p>
            <textarea name="memory" rows="14" dir="auto" class="w-full rounded-lg border-slate-300 font-mono text-sm" placeholder="{{ __('Empty. It will be written on the next AI analysis.') }}">{{ $project->memory }}</textarea>
            <button class="px-4 py-1.5 rounded-lg bg-slate-900 text-white">{{ __('Save') }}</button>
        </form>

        @if ($revisions->isNotEmpty())
            <details class="rounded-lg bg-slate-50 p-3">
                <summary class="cursor-pointer text-xs font-semibold text-slate-500">{{ __('History') }} ({{ $revisions->count() }})</summary>
                <ul class="mt-2 space-y-2">
                    @foreach ($revisions as $rev)
                        <li x-data="{ open: false }" class="border-t border-slate-200 pt-2">
                            <div class="flex items-center justify-between gap-2 text-xs">
                                <button type="button" @click="open = !open" class="text-slate-600 hover:underline">{{ $rev->created_at->diffForHumans() }} · {{ $rev->source === 'ai' ? 'AI' : __('Admin') }}</button>
                                @if ($rev->body !== $project->memory)
                                    <form method="post" action="{{ route('admin.projects.memory.restore', [$project, $rev]) }}">@csrf<button class="px-2 py-0.5 rounded border border-slate-300 hover:bg-white">{{ __('Restore') }}</button></form>
                                @else
                                    <span class="text-emerald-600">{{ __('Current') }}</span>
                                @endif
                            </div>
                            <pre x-show="open" x-cloak dir="auto" class="mt-2 whitespace-pre-wrap text-xs text-slate-600 font-mono">{{ $rev->body }}</pre>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
    </div>
</section>
