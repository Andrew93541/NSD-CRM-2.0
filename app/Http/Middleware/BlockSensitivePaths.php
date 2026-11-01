<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockSensitivePaths
{
    private const BLOCKED_PATTERN = '#^/(?:\.(?!well-known(?:/|$))|artisan$|composer\.(?:json|lock)$|package(?:-lock)?\.json$|phpunit\.xml|vite\.config\.js$|(?:app|bootstrap|config|database|docs|lang|node_modules|resources|routes|storage|tests|vendor)(?:/|$)|.*\.(?:sql|sqlite|sqlite3|log|bak|env)$)#i';

    public function handle(Request $request, Closure $next): Response
    {
        $path = '/' . ltrim($request->getPathInfo(), '/');

        if (preg_match(self::BLOCKED_PATTERN, $path)) {
            abort(403, 'Access to protected system path is forbidden.');
        }

        return $next($request);
    }
}

