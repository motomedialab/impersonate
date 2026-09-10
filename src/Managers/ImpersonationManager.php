<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Managers;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Application;
use Illuminate\Session\SessionManager;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Motomedialab\Impersonate\Services\ActorResolver;
use Motomedialab\Impersonate\Actions\EndImpersonation;
use Motomedialab\Impersonate\Contracts\CanImpersonate;
use Motomedialab\Impersonate\Actions\BeginImpersonation;
use Motomedialab\Impersonate\Actions\DetermineReturnUrl;
use Motomedialab\Impersonate\Contracts\CanBeImpersonated;
use Motomedialab\Impersonate\Actions\DetermineRedirectUrl;
use Motomedialab\Impersonate\Contracts\ImpersonatableUser;
use Motomedialab\Impersonate\Exceptions\ImpersonationException;
use Motomedialab\Impersonate\ValueObjects\ImpersonationSession;
use Motomedialab\Impersonate\Actions\ValidateImpersonationSession;

final class ImpersonationManager
{
    private string $impersonationKey = 'impersonationId';

    private string $referrerKey = 'impersonatorReferrer';

    private readonly Application $app;

    private ?Closure $redirectToCallback = null;

    private ?Closure $returnToCallback = null;

    public function __construct(Closure $callback)
    {
        $this->app = $callback();
    }

    public function isImpersonating(): bool
    {
        return $this->getSession() !== null;
    }

    public function getUserId(): ?int
    {
        return $this->getSession()?->targetId;
    }

    public function getAuthGuard(): ?string
    {
        return $this->getSession()?->targetGuard;
    }

    public function getActorUserId(): ?int
    {
        return $this->getSession()?->actorId;
    }

    public function getActorAuthGuard(): ?string
    {
        return $this->getSession()?->actorGuard;
    }

    public function getSession(): ?ImpersonationSession
    {
        try {
            $data = $this->session()->get($this->impersonationKey);

            return ImpersonationSession::fromSession($data);
        } catch (\Throwable) {
            return null;
        }
    }

    public function impersonate(int $impersonationId, string $guard, ?int $actorId = null, ?string $actorGuard = null): void
    {
        $impersonationSession = new ImpersonationSession(
            targetId: $impersonationId,
            targetGuard: $guard,
            actorId: $actorId,
            actorGuard: $actorGuard,
        );

        $this->session()->put($this->impersonationKey, $impersonationSession->toArray());
        auth($guard)->onceUsingId($impersonationId);
    }

    public function setReferrer(string $url): void
    {
        $this->session()->put($this->referrerKey, $url);
    }

    public function getReferrer(): ?string
    {
        $referrer = $this->session()->get($this->referrerKey);

        return is_string($referrer) ? $referrer : null;
    }

    public function clearSession(): void
    {
        $this->session()->remove($this->referrerKey);
        $this->session()->remove($this->impersonationKey);
        $this->session()->regenerate();
    }

    public function endImpersonation(): void
    {
        $this->app->make(EndImpersonation::class)();
    }

    public function beginImpersonation(
        CanImpersonate|ImpersonatableUser $actor,
        int|CanBeImpersonated|ImpersonatableUser $target,
        ?string $guard = null,
        ?string $actorGuard = null,
    ): void {
        $guard ??= config('auth.defaults.guard');

        $targetUser = $target instanceof CanBeImpersonated || $target instanceof ImpersonatableUser
            ? $target
            : $this->findUser($target, $guard);

        if (! $targetUser instanceof CanBeImpersonated && ! $targetUser instanceof ImpersonatableUser) {
            throw new ImpersonationException('The provided user cannot be impersonated');
        }

        $this->app->make(BeginImpersonation::class)($actor, $targetUser, $guard, $actorGuard);
    }

    public function findActorGuard(Authenticatable $actor): string
    {
        return $this->app->make(ActorResolver::class)->resolveGuard($actor);
    }

    public function findUser(int $id, ?string $guard = null): ?Authenticatable
    {
        return $this->userProvider($guard)->retrieveById($id);
    }

    public function validateImpersonationSession(?Authenticatable $actor): bool
    {
        return $this->app->make(ValidateImpersonationSession::class)($actor);
    }

    public function redirectTo(Closure $callback): void
    {
        $this->redirectToCallback = $callback;
    }

    public function returnTo(Closure $callback): void
    {
        $this->returnToCallback = $callback;
    }

    public function getRedirectToCallback(): ?Closure
    {
        return $this->redirectToCallback;
    }

    public function getReturnToCallback(): ?Closure
    {
        return $this->returnToCallback;
    }

    public function flushCallbacks(): void
    {
        $this->redirectToCallback = null;
        $this->returnToCallback = null;
    }

    public function getRedirectUrl(
        CanBeImpersonated|ImpersonatableUser $target,
        CanImpersonate|ImpersonatableUser $actor
    ): string {
        return $this->app->make(DetermineRedirectUrl::class)($target, $actor);
    }

    public function getReturnUrl(CanImpersonate|ImpersonatableUser $actor): string
    {
        return $this->app->make(DetermineReturnUrl::class)($actor);
    }

    private function session(): SessionManager
    {
        return $this->app->get(SessionManager::class);
    }

    private function userProvider(?string $guard = null): UserProvider
    {
        $guard ??= config('auth.defaults.guard');

        return Auth::createUserProvider(config('auth.guards.'.$guard.'.provider'));
    }
}
