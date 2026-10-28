<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetUserTheme
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $theme = $request->session()->get('theme');

        if (!$theme && $request->hasCookie('theme')) {
            $cookieTheme = $request->cookie('theme');
            if (in_array($cookieTheme, ['light', 'dark', 'system'], true)) {
                $theme = $cookieTheme;
                $request->session()->put('theme', $theme);
            }
        }

        if (!in_array($theme, ['light', 'dark', 'system'], true)) {
            // Default to dark as per NSD industrial baseline
            $theme = 'dark';
        }

        View::share('currentTheme', $theme);

        return $next($request);
    }
}
