<?php

declare(strict_types=1);

namespace EricGansa\FilterManagerBundle\Contract;

/**
 * Resolves the currently authenticated user.
 *
 * This abstraction decouples FilterManager from Symfony SecurityBundle,
 * allowing the bundle to function in projects without authentication.
 */
interface SecurityUserResolverInterface
{
    /**
     * Returns the currently authenticated user, or null if unauthenticated
     * or if SecurityBundle is not installed.
     */
    public function getCurrentUser(): ?object;
}
