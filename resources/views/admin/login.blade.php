<x-layouts.app :title="__('Admin login')">
    <div class="max-w-sm mx-auto mt-16">
        <h1 class="text-xl font-bold mb-4">{{ __('Admin login') }}</h1>
        <form method="post" action="{{ route('admin.login') }}" class="bg-white rounded-xl border border-slate-200 p-6 space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Email') }}</label>
                <input name="email" type="email" value="{{ old('email') }}" required dir="ltr" class="w-full rounded-lg border-slate-300" />
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Password') }}</label>
                <input name="password" type="password" required dir="ltr" class="w-full rounded-lg border-slate-300" />
            </div>
            <button class="w-full bg-slate-900 text-white py-2.5 rounded-lg font-medium">{{ __('Sign in') }}</button>
        </form>
    </div>
</x-layouts.app>
