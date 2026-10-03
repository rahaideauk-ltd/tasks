@props(['status'])
@php($cls = match ($status) {
    'draft' => 'bg-slate-100 text-slate-700',
    'todo' => 'bg-blue-50 text-blue-700',
    'submitted' => 'bg-amber-50 text-amber-800',
    'not_done' => 'bg-orange-50 text-orange-800',
    'approved' => 'bg-emerald-50 text-emerald-700',
    'rejected' => 'bg-red-50 text-red-700',
    'dropped' => 'bg-slate-100 text-slate-500 line-through',
    default => 'bg-slate-100 text-slate-700',
})
<span {{ $attributes->merge(['class' => "inline-block text-xs px-2 py-0.5 rounded-full $cls"]) }}>{{ __("status.$status") }}</span>
