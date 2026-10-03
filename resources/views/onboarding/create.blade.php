<x-layouts.app :title="__('New project')">
    <div class="max-w-xl mx-auto">
        <h1 class="text-2xl font-bold text-slate-900 mb-1">{{ __('Tell us about your business') }}</h1>
        <p class="text-slate-500 text-sm mb-6">{{ __('The more you share, the more specific your tasks will be.') }}</p>

        <form method="post" action="{{ route('onboarding.store') }}" class="bg-white rounded-xl border border-slate-200 p-6 space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Business name') }} *</label>
                <input name="name" value="{{ old('name') }}" required class="w-full rounded-lg border-slate-300" />
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Website address') }}</label>
                <input name="site_url" value="{{ old('site_url') }}" placeholder="https://example.com" dir="ltr" class="w-full rounded-lg border-slate-300" />
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Industry / what you sell') }}</label>
                <input name="industry" value="{{ old('industry') }}" class="w-full rounded-lg border-slate-300" />
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Describe your business') }}</label>
                <textarea name="description" rows="4" class="w-full rounded-lg border-slate-300" placeholder="{{ __('Who are your customers? Where are you based? What makes you different?') }}">{{ old('description') }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Goals') }}</label>
                <textarea name="goals" rows="3" class="w-full rounded-lg border-slate-300" placeholder="{{ __('e.g. more phone calls, more online orders, rank for a keyword') }}">{{ old('goals') }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Email (optional)') }}</label>
                <input name="contact_email" type="email" value="{{ old('contact_email') }}" dir="ltr" class="w-full rounded-lg border-slate-300" />
            </div>
            <button class="w-full bg-slate-900 text-white py-3 rounded-lg font-medium hover:bg-slate-700">{{ __('Create my project') }}</button>
            <p class="text-xs text-slate-500">{{ __('You will get a private link. Keep it: it is the only way back to your tasks.') }}</p>
        </form>
    </div>
</x-layouts.app>
