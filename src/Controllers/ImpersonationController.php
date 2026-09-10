<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Motomedialab\Impersonate\Contracts\CanImpersonate;
use Motomedialab\Impersonate\Contracts\CanBeImpersonated;
use Motomedialab\Impersonate\Contracts\ImpersonatableUser;
use Motomedialab\Impersonate\Managers\ImpersonationManager;
use Motomedialab\Impersonate\Exceptions\ImpersonationException;

final class ImpersonationController
{
    public function begin(Request $request, ImpersonationManager $manager, int $id, ?string $guard = null): RedirectResponse
    {
        if ($guard !== null && ! array_key_exists($guard, config('auth.guards', []))) {
            return back()->withErrors(['error' => 'The specified authentication guard does not exist.']);
        }

        $actorGuard = $request->input('actor_guard');
        if (is_string($actorGuard) && ! array_key_exists($actorGuard, config('auth.guards', []))) {
            return back()->withErrors(['error' => 'The specified actor guard does not exist.']);
        }

        $actor = is_string($actorGuard) ? $request->user($actorGuard) : null;

        if ($actor === null) {
            $actor = $request->user();
        }

        if (! $actor instanceof CanImpersonate && ! $actor instanceof ImpersonatableUser) {
            foreach (array_keys(config('auth.guards', [])) as $possibleGuard) {
                if (auth((string) $possibleGuard)->check()) {
                    $candidate = auth((string) $possibleGuard)->user();
                    if ($candidate instanceof CanImpersonate || $candidate instanceof ImpersonatableUser) {
                        $actor = $candidate;
                        $actorGuard = (string) $possibleGuard;
                        break;
                    }
                }
            }
        }

        if (! $actor instanceof CanImpersonate && ! $actor instanceof ImpersonatableUser) {
            return back()->withErrors(['error' => 'The currently authenticated user cannot impersonate.']);
        }

        $target = $manager->findUser($id, $guard);
        if (! $target instanceof CanBeImpersonated && ! $target instanceof ImpersonatableUser) {
            return back()->withErrors(['error' => 'The target user cannot be impersonated.']);
        }

        try {
            $manager->beginImpersonation($actor, $target, $guard, $actorGuard);
        } catch (ImpersonationException $impersonationException) {
            return back()->withErrors(['error' => $impersonationException->getMessage()]);
        }

        return redirect()->to($manager->getRedirectUrl($target, $actor));
    }

    public function end(Request $request, ImpersonationManager $manager): RedirectResponse
    {
        if (! $manager->isImpersonating()) {
            return back();
        }

        $actorGuard = $manager->getActorAuthGuard();
        $actor = ($actorGuard ? $request->user($actorGuard) : null) ?? $request->user();

        if (! $actor instanceof CanImpersonate && ! $actor instanceof ImpersonatableUser) {
            $manager->endImpersonation();

            return redirect()->to('/');
        }

        $returnUrl = $manager->getReturnUrl($actor);
        $manager->endImpersonation();

        return redirect()->to($returnUrl);
    }
}
