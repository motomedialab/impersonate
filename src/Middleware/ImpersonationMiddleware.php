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
        /** @var ImpersonationManager $manager */
        $manager = resolve(ImpersonationManager::class);

        if ($manager->isImpersonating()) {
            $guard = $manager->getAuthGuard();
            $actorGuard = $manager->getActorAuthGuard() ?? $guard;

            $actor = $request->user($actorGuard);

            if (! $manager->validateImpersonationSession($actor)) {
                $manager->endImpersonation();

                return $next($request);
            }

            // apply the impersonation for the duration of this request.
            $manager->impersonate(
                $manager->getUserId(),
                $guard,
                $manager->getActorUserId(),
                $actorGuard
            );
        }

        return $next($request);
    }
}
