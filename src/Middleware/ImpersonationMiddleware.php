<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Middleware;

use Closure;
use Illuminate\Http\Request;
use Motomedialab\Impersonate\Managers\ImpersonationManager;

final class ImpersonationMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        /** @var ImpersonationManager $impersonation */
        $impersonation = resolve('impersonation');

        if ($impersonation->isImpersonating()) {
            $guard = $impersonation->getAuthGuard();

            if (! $impersonation->validateImpersonationSession($request->user($guard))) {
                $impersonation->endImpersonation();

                return $next($request);
            }

            // We apply the impersonation for the duration of this request.
            $impersonation->impersonate($impersonation->getUserId(), $guard);
        }

        return $next($request);
    }
}
