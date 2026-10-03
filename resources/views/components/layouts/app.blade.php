@php($rtl = app()->getLocale() === 'fa')
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('Task Portal') }} · {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700&family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: {{ $rtl ? "'Vazirmatn'" : "'Inter'" }}, system-ui, sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
<header class="bg-white border-b border-slate-200">
    <div class="max-w-5xl mx-auto px-4 h-14 flex items-center justify-between gap-4">
        <a href="{{ $homeUrl ?? route('home') }}" class="font-bold text-slate-900">{{ config('app.name') }}</a>
        <div class="flex items-center gap-3 text-sm">
            {{ $nav ?? '' }}
            <a href="{{ request()->fullUrlWithQuery(['lang' => $rtl ? 'en' : 'fa']) }}" class="px-2 py-1 rounded border border-slate-300 hover:bg-slate-100">{{ $rtl ? 'English' : 'فارسی' }}</a>
        </div>
    </div>
</header>

<main class="max-w-5xl mx-auto px-4 py-6">
    @if (session('ok'))
        <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('ok') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">
            <ul class="list-disc {{ $rtl ? 'mr-4' : 'ml-4' }}">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    {{ $slot }}
</main>
</body>
</html>
