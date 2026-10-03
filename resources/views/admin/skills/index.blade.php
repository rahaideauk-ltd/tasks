<x-layouts.app :title="__('Skills')" :homeUrl="route('admin.projects.index')">
    <x-slot:nav>
        <a href="{{ route('admin.projects.index') }}" class="text-slate-500 hover:text-slate-900">{{ __('Projects') }}</a>
        <form method="post" action="{{ route('admin.logout') }}">@csrf<button class="text-slate-500 hover:text-slate-900">{{ __('Log out') }}</button></form>
    </x-slot:nav>

    <div class="flex items-center justify-between mb-2">
        <h1 class="text-2xl font-bold">{{ __('Skills') }}</h1>
    </div>
    <p class="text-sm text-slate-500 mb-4">{{ __('Playbooks Claude follows when it suggests tasks, e.g. how we do local SEO or how we write a product page. A global skill is used for every project; a project skill only for that project and overrides a global one with the same slug.') }}</p>

    <div class="space-y-3 mb-8">
        @forelse ($skills as $skill)
            <article class="bg-white rounded-xl border border-slate-200 p-4 text-sm {{ $skill->active ? '' : 'opacity-60' }}" x-data="{ edit: false }">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="font-semibold">{{ $skill->name }}</h2>
                            <code class="text-xs text-slate-400" dir="ltr">{{ $skill->slug }}</code>
                            <span class="text-[10px] rounded px-1.5 {{ $skill->project ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-600' }}">{{ $skill->project?->name ?? __('All projects') }}</span>
                            @unless ($skill->active)<span class="text-[10px] rounded px-1.5 bg-slate-100 text-slate-500">{{ __('Inactive') }}</span>@endunless
                            <span class="text-xs text-slate-400">{{ __(':n tasks', ['n' => $skill->tasks_count]) }}</span>
                        </div>
                        @if ($skill->description)<p class="text-slate-600 mt-1">{{ $skill->description }}</p>@endif
                    </div>
                    <div class="flex gap-1 shrink-0">
                        <button type="button" @click="edit = !edit" class="px-2 py-1 rounded border border-slate-300 hover:bg-slate-50">✎</button>
                        <form method="post" action="{{ route('admin.skills.destroy', $skill) }}" onsubmit="return confirm('{{ __('Delete this skill?') }}')">@csrf @method('delete')<button class="px-2 py-1 rounded border border-slate-300 text-red-600 hover:bg-red-50">✕</button></form>
                    </div>
                </div>
                <div x-show="edit" x-cloak class="mt-3 border-t border-slate-100 pt-3">
                    @include('admin.skills._form', ['skill' => $skill])
                </div>
            </article>
        @empty
            <div class="text-sm text-slate-500 bg-white rounded-xl border border-dashed border-slate-300 p-6 text-center">{{ __('No skills yet.') }}</div>
        @endforelse
    </div>

    <section class="bg-white rounded-xl border border-slate-200 p-4 text-sm">
        <h2 class="font-semibold mb-3">{{ __('New skill') }}</h2>
        @include('admin.skills._form', ['skill' => null])
    </section>
</x-layouts.app>
