<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;
use Illuminate\Contracts\Auth\Authenticatable;
use Motomedialab\Impersonate\Contracts\CanImpersonate;
use Motomedialab\Impersonate\Contracts\CanBeImpersonated;
use Motomedialab\Impersonate\Managers\ImpersonationManager;
use Motomedialab\Impersonate\ValueObjects\ImpersonationSession;

/**
 * @method static bool isImpersonating()
 * @method static int|null getUserId()
 * @method static string|null getAuthGuard()
 * @method static int|null getActorUserId()
 * @method static string|null getActorAuthGuard()
 * @method static ImpersonationSession|null getSession()
 * @method static void impersonate(int $impersonationId, string $guard, ?int $actorId = null, ?string $actorGuard = null)
 * @method static void endImpersonation()
 * @method static void beginImpersonation(CanImpersonate $actor, int|CanBeImpersonated $target, ?string $guard = null, ?string $actorGuard = null)
 * @method static string findActorGuard(Authenticatable $actor)
 * @method static Authenticatable|null findUser(int $id, ?string $guard = null)
 * @method static bool validateImpersonationSession(?Authenticatable $actor)
 * @method static string getRedirectUrl(CanBeImpersonated $target, CanImpersonate $actor)
 * @method static string getReturnUrl(CanImpersonate $actor)
 * @method static void redirectTo(Closure $callback)
 * @method static void returnTo(Closure $callback)
 * @method static void flushCallbacks()
 *
 * @see ImpersonationManager
 */
final class Impersonate extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ImpersonationManager::class;
    }
}
