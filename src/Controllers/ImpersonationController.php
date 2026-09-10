<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Motomedialab\Impersonate\Services\ActorResolver;
use Motomedialab\Impersonate\Contracts\CanImpersonate;
use Motomedialab\Impersonate\Contracts\CanBeImpersonated;
use Motomedialab\Impersonate\Managers\ImpersonationManager;
use Motomedialab\Impersonate\Exceptions\ImpersonationException;

final class ImpersonationController
{
    public function begin(
        Request $request,
        ImpersonationManager $manager,
        ActorResolver $actorResolver,
        int $id,
        ?string $guard = null
    ): RedirectResponse {
        if ($guard !== null && ! array_key_exists($guard, config('auth.guards', []))) {
            return back()->withErrors(['error' => 'The specified authentication guard does not exist.']);
        }

        $actorGuard = $request->input('actor_guard');
        if (is_string($actorGuard) && ! array_key_exists($actorGuard, config('auth.guards', []))) {
            return back()->withErrors(['error' => 'The specified actor guard does not exist.']);
        }

        $actor = $actorResolver->resolve($request, is_string($actorGuard) ? $actorGuard : null);

        if (! $actor instanceof CanImpersonate) {
            return back()->withErrors(['error' => 'The currently authenticated user cannot impersonate.']);
        }

        $target = $manager->findUser($id, $guard);
        if (! $target instanceof CanBeImpersonated) {
            return back()->withErrors(['error' => 'The target user cannot be impersonated.']);
        }

        try {
            $manager->beginImpersonation($actor, $target, $guard, is_string($actorGuard) ? $actorGuard : null);
        } catch (ImpersonationException $impersonationException) {
            return back()->withErrors(['error' => $impersonationException->getMessage()]);
        }

        return redirect()->to($manager->getRedirectUrl($target, $actor));
    }

    public function end(
        Request $request,
        ImpersonationManager $manager,
        ActorResolver $actorResolver
    ): RedirectResponse {
        if (! $manager->isImpersonating()) {
            return back();
        }

        $actor = $actorResolver->resolve($request, $manager->getActorAuthGuard());

        if (! $actor instanceof CanImpersonate) {
            $manager->endImpersonation();

            return redirect()->to('/');
        }

        $returnUrl = $manager->getReturnUrl($actor);
        $manager->endImpersonation();

        return redirect()->to($returnUrl);
    }
}
