<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** ?lang=fa|en switches the UI language and remembers it in the session. */
class SetLocale
{
    public const LOCALES = ['fa', 'en'];

    public function handle(Request $request, Closure $next)
    {
        if ($request->filled('lang') && in_array($request->query('lang'), self::LOCALES, true)) {
            $request->session()->put('locale', $request->query('lang'));
        }
        app()->setLocale($request->session()->get('locale', config('app.locale')));

        return $next($request);
    }
}
