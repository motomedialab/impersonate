<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Managers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Application;
use Illuminate\Session\SessionManager;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Motomedialab\Impersonate\Events\ImpersonateBegun;
use Motomedialab\Impersonate\Events\ImpersonateEnded;
use Motomedialab\Impersonate\Contracts\ImpersonatableUser;
use Motomedialab\Impersonate\Exceptions\ImpersonationException;

class ImpersonationManager
{
    private string $impersonationKey = 'impersonationId';

    private string $referrerKey = 'impersonatorReferrer';

    private readonly Application $app;

    public function __construct(\Closure $callback)
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

    public function impersonate(int $impersonationId, string $guard): void
    {
        $this->session()->put($this->impersonationKey, $guard.'::'.$impersonationId);
        auth($guard)->onceUsingId($impersonationId);
    }

    public function endImpersonation(): void
    {
        $user = auth()->user();
        if ($user instanceof ImpersonatableUser) {
            event(new ImpersonateEnded($user));
        }

        $this->session()->remove($this->referrerKey);
        $this->session()->remove($this->impersonationKey);
    }

    public function beginImpersonation(Authenticatable $currentUser, int $userId, ?string $guard): void
    {
        $guard ??= config('auth.defaults.guard');
        $user = $this->findUser($userId, $guard);

        throw_if($this->isImpersonating(), ImpersonationException::class, 'An active impersonation session is already running');

        // check if the current user can impersonate.
        throw_unless($currentUser instanceof ImpersonatableUser, ImpersonationException::class, 'The currently authenticated user must implement ImpersonatableUser');

        // check we have a user we can impersonate
        throw_unless($user instanceof ImpersonatableUser, ImpersonationException::class, 'The provided user cannot be impersonated');

        throw_unless($user->canBeImpersonatedBy($currentUser), ImpersonationException::class, 'The provided user cannot be impersonated');

        // check our current user is able to impersonate them.
        throw_unless($currentUser->canImpersonate($user), ImpersonationException::class, 'The currently authenticated user doesnt have permission to impersonate user with ID '.$userId);

        $this->session()->put($this->referrerKey, url()->previous());
        $this->impersonate($userId, $guard);

        event(new ImpersonateBegun($user, $currentUser));
    }

    public function findUser(int $id, ?string $guard = null): ?Authenticatable
    {
        return $this->userProvider($guard)->retrieveById($id);
    }

    public function validateImpersonationSession(?Authenticatable $currentUser): bool
    {
        if (! $currentUser instanceof ImpersonatableUser) {
            return false;
        }

        $targetUser = $this->findUser($this->getUserId(), $this->getAuthGuard());

        if (! $targetUser instanceof ImpersonatableUser) {
            return false;
        }

        return $targetUser->canBeImpersonatedBy($currentUser)
            && $currentUser->canImpersonate($targetUser);
    }

    public function getRedirectUrl(): string
    {
        // the URL to redirect to when beginning impersonation
        return config('impersonate.redirect_to') ?? '/';
    }

    public function getReturnUrl(): string
    {
        return config('impersonate.return_to')
            ?? $this->session()->get($this->referrerKey)
            ?? '/';
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
        return Auth::createUserProvider(config('auth.guards.'.$guard.'.provider'));
    }
}
