<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Managers;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Application;
use Illuminate\Session\SessionManager;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Motomedialab\Impersonate\Actions\EndImpersonation;
use Motomedialab\Impersonate\Contracts\CanImpersonate;
use Motomedialab\Impersonate\Actions\BeginImpersonation;
use Motomedialab\Impersonate\Actions\DetermineReturnUrl;
use Motomedialab\Impersonate\Contracts\CanBeImpersonated;
use Motomedialab\Impersonate\Actions\DetermineRedirectUrl;
use Motomedialab\Impersonate\Contracts\ImpersonatableUser;
use Motomedialab\Impersonate\Exceptions\ImpersonationException;
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
        return is_numeric($this->getUserId());
    }

    public function getUserId(): ?int
    {
        $data = $this->getSessionData();

        return array_key_exists(1, $data ?? []) ? (int) $data[1] : null;
    }

    public function getAuthGuard(): ?string
    {
        return $this->getSessionData()[0] ?? null;
    }

    public function getActorUserId(): ?int
    {
        $data = $this->getSessionData();

        return array_key_exists(3, $data ?? []) ? (int) $data[3] : null;
    }

    public function getActorAuthGuard(): ?string
    {
        return $this->getSessionData()[2] ?? null;
    }

    public function impersonate(int $impersonationId, string $guard, ?int $actorId = null, ?string $actorGuard = null): void
    {
        $sessionValue = $actorId !== null && $actorGuard !== null
            ? "{$guard}::{$impersonationId}::{$actorGuard}::{$actorId}"
            : "{$guard}::{$impersonationId}";

        $this->session()->put($this->impersonationKey, $sessionValue);
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
        /** @var array<string, mixed> $guards */
        $guards = config('auth.guards', []);

        foreach (array_keys($guards) as $guard) {
            $guard = (string) $guard;
            $guardInstance = Auth::guard($guard);

            if ($guardInstance->check() && $guardInstance->id() === $actor->getAuthIdentifier()) {
                return $guard;
            }
        }

        return (string) config('auth.defaults.guard', 'web');
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

    /**
     * @return array<int, string>|null
     */
    private function getSessionData(): ?array
    {
        try {
            $data = $this->session()->get($this->impersonationKey);

            return empty($data) ? null : explode('::', (string) $data);
        } catch (\Throwable) {
            //
        }

        return null;
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
