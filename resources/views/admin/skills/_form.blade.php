<form method="post" action="{{ $skill ? route('admin.skills.update', $skill) : route('admin.skills.store') }}" class="grid md:grid-cols-2 gap-3">
    @csrf
    @if ($skill) @method('put') @endif
    <label>{{ __('Name') }}<input name="name" value="{{ $skill?->name }}" required class="w-full rounded-lg border-slate-300 mt-1" placeholder="{{ __('e.g. Local SEO') }}"></label>
    <label>{{ __('Slug') }}<input name="slug" value="{{ $skill?->slug }}" dir="ltr" class="w-full rounded-lg border-slate-300 mt-1" placeholder="local-seo"></label>
    <label class="md:col-span-2">{{ __('When to use') }}<input name="description" value="{{ $skill?->description }}" class="w-full rounded-lg border-slate-300 mt-1" placeholder="{{ __('e.g. Businesses that serve customers in one city') }}"></label>
    <label class="md:col-span-2">{{ __('Instructions for Claude') }}
        <textarea name="instructions" rows="8" required dir="auto" class="w-full rounded-lg border-slate-300 mt-1 font-mono text-sm" placeholder="{{ __('Steps, checks, standards and examples Claude should follow for tasks of this kind.') }}">{{ $skill?->instructions }}</textarea>
    </label>
    <label>{{ __('Project') }}
        <select name="project_id" class="w-full rounded-lg border-slate-300 mt-1">
            <option value="">{{ __('All projects') }}</option>
            @foreach ($projects as $p)<option value="{{ $p->id }}" @selected($skill?->project_id === $p->id)>{{ $p->name }}</option>@endforeach
        </select>
    </label>
    <label class="flex items-center gap-2 self-end pb-2"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked($skill?->active ?? true) class="rounded border-slate-300"> {{ __('Active') }}</label>
    <div class="md:col-span-2"><button class="px-4 py-1.5 rounded-lg bg-slate-900 text-white">{{ __('Save') }}</button></div>
</form>
