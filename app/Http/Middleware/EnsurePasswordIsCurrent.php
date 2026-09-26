<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Actions\PasswordExpiry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Sends users with a forced-change or expired password to the change-password screen. */
class EnsurePasswordIsCurrent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && PasswordExpiry::mustChange($user)) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
