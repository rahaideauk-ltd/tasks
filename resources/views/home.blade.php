<x-layouts.app :title="__('Welcome')">
    <x-slot:nav><a href="{{ route('login') }}" class="text-slate-500 hover:text-slate-900">{{ __('Admin') }}</a></x-slot:nav>
    <div class="max-w-xl mx-auto text-center py-16">
        <h1 class="text-3xl font-bold text-slate-900 mb-4">{{ __('Grow your website, one task at a time') }}</h1>
        <p class="text-slate-600 mb-8">{{ __('Tell us about your business and website. We analyse it, connect your analytics, and give you a clear checklist of what to do next. Do the tasks, we review them, and the next round follows.') }}</p>
        <a href="{{ route('onboarding.create') }}" class="inline-block bg-slate-900 text-white px-6 py-3 rounded-lg font-medium hover:bg-slate-700">{{ __('Start my project') }}</a>
    </div>
</x-layouts.app>
