<article class="bg-white rounded-xl border border-slate-200 p-3 text-sm" x-data="{ edit: false }">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
                <h4 class="font-semibold">{{ $task->title() }}</h4>
                <x-priority :priority="$task->priority" />
                <x-status-badge :status="$task->status" />
                <span class="text-[10px] uppercase text-slate-400">{{ $task->source }}</span>
            </div>
            <p class="text-slate-600 mt-1 whitespace-pre-line">{{ $task->description() }}</p>
            @if ($task->reason)<p class="text-xs text-slate-400 mt-1">{{ __('Why') }}: {{ $task->reason }}</p>@endif
            @if ($task->client_note)<p class="text-xs mt-1 text-slate-600"><span class="font-medium">{{ __('Client note') }}:</span> {{ $task->client_note }}</p>@endif
            @if ($task->admin_feedback)<p class="text-xs mt-1 text-slate-600"><span class="font-medium">{{ __('Our feedback') }}:</span> {{ $task->admin_feedback }}</p>@endif
        </div>
        <div class="flex gap-1 shrink-0">
            @if ($task->status === 'draft')
                <form method="post" action="{{ route('admin.tasks.review', $task) }}">@csrf<input type="hidden" name="decision" value="publish"><button class="px-2 py-1 rounded border border-slate-300 hover:bg-slate-50" title="{{ __('Publish to current round') }}">↑</button></form>
            @endif
            <button type="button" @click="edit = !edit" class="px-2 py-1 rounded border border-slate-300 hover:bg-slate-50">✎</button>
            <form method="post" action="{{ route('admin.tasks.destroy', $task) }}" onsubmit="return confirm('{{ __('Delete this task?') }}')">@csrf @method('delete')<button class="px-2 py-1 rounded border border-slate-300 text-red-600 hover:bg-red-50">✕</button></form>
        </div>
    </div>
    <form x-show="edit" x-cloak method="post" action="{{ route('admin.tasks.update', $task) }}" class="mt-3 grid md:grid-cols-2 gap-2 border-t border-slate-100 pt-3">
        @csrf @method('put')
        <input name="title_fa" value="{{ $task->title_fa }}" class="rounded-lg border-slate-300" placeholder="{{ __('Title (fa)') }}">
        <input name="title_en" value="{{ $task->title_en }}" dir="ltr" class="rounded-lg border-slate-300" placeholder="{{ __('Title (en)') }}">
        <textarea name="description_fa" rows="3" class="rounded-lg border-slate-300">{{ $task->description_fa }}</textarea>
        <textarea name="description_en" rows="3" dir="ltr" class="rounded-lg border-slate-300">{{ $task->description_en }}</textarea>
        <input name="category" value="{{ $task->category?->name_en }}" list="cats" class="rounded-lg border-slate-300" placeholder="{{ __('Category') }}">
        <select name="priority" class="rounded-lg border-slate-300">@foreach (['high','medium','low'] as $p)<option value="{{ $p }}" @selected($task->priority === $p)>{{ __("priority.$p") }}</option>@endforeach</select>
        <div class="md:col-span-2"><button class="px-4 py-1.5 rounded-lg bg-slate-900 text-white">{{ __('Save') }}</button></div>
    </form>
</article>
