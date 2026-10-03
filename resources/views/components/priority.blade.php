@props(['priority'])
@php($cls = match ($priority) { 'high' => 'text-red-600', 'low' => 'text-slate-400', default => 'text-amber-600' })
<span class="text-xs font-medium {{ $cls }}">{{ __("priority.$priority") }}</span>
