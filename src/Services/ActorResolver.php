<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Auth\Authenticatable;
use Motomedialab\Impersonate\Contracts\CanImpersonate;
use Motomedialab\Impersonate\Contracts\ImpersonatableUser;

final class ActorResolver
{
    /**
     * Resolve the authenticatable actor initiating or ending impersonation.
     */
    public function resolve(Request $request, ?string $preferredGuard = null): ?Authenticatable
    {
        if ($preferredGuard !== null && array_key_exists($preferredGuard, config('auth.guards', []))) {
            $user = $request->user($preferredGuard);
            if ($user !== null) {
                return $user;
            }
        }

        $defaultUser = $request->user();
        if ($this->canImpersonate($defaultUser)) {
            return $defaultUser;
        }

        // Check other active guards if default user cannot impersonate
        /** @var array<string, mixed> $guards */
        $guards = config('auth.guards', []);

        foreach (array_keys($guards) as $guard) {
            $guard = (string) $guard;
            $guardInstance = Auth::guard($guard);

            if ($guardInstance->check()) {
                $candidate = $guardInstance->user();
                if ($this->canImpersonate($candidate)) {
                    return $candidate;
                }
            }
        }

        return $defaultUser;
    }

    /**
     * Detect the guard on which the given actor is actively authenticated.
     */
    public function resolveGuard(Authenticatable $actor): string
    {
        /** @var array<string, mixed> $guards */
        $guards = config('auth.guards', []);

        foreach (array_keys($guards) as $guard) {
            $guard = (string) $guard;
            $guardInstance = Auth::guard($guard);

            if ($guardInstance->check()) {
                $guardUser = $guardInstance->user();
                if ($guardUser !== null
                    && get_class($guardUser) === get_class($actor)
                    && $guardUser->getAuthIdentifier() === $actor->getAuthIdentifier()) {
                    return $guard;
                }
            }
        }

        return (string) config('auth.defaults.guard', 'web');
    }

    private function canImpersonate(?Authenticatable $user): bool
    {
        return $user instanceof CanImpersonate || $user instanceof ImpersonatableUser;
    }
}
