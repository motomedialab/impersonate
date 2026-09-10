<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Middleware;

use Closure;
use Illuminate\Http\Request;
use Motomedialab\Impersonate\Services\ActorResolver;
use Motomedialab\Impersonate\Managers\ImpersonationManager;

final class ImpersonationMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        /** @var ImpersonationManager $manager */
        $manager = resolve(ImpersonationManager::class);

        $session = $manager->getSession();

        if ($session !== null) {
            $actorGuard = $session->actorGuard ?? $session->targetGuard;

            /** @var ActorResolver $actorResolver */
            $actorResolver = resolve(ActorResolver::class);
            $actor = $actorResolver->resolve($request, $actorGuard);

            if (! $manager->validateImpersonationSession($actor)) {
                $manager->endImpersonation();

                return $next($request);
            }

            // Apply the impersonation for the duration of this request
            $manager->impersonate(
                $session->targetId,
                $session->targetGuard,
                $session->actorId,
                $session->actorGuard
            );
        }

        return $next($request);
    }
}
