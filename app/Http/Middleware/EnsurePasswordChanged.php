<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** User dengan password sementara wajib menggantinya sebelum memakai aplikasi. */
class EnsurePasswordChanged
{
    private const ALLOWED = ['account.password', 'account.password.update', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password && ! in_array($request->route()?->getName(), self::ALLOWED, true)) {
            return redirect()->route('account.password')->with('status', 'Ganti password sementara Anda terlebih dahulu.');
        }

        return $next($request);
    }
}
