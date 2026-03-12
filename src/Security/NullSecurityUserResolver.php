<?php

declare(strict_types=1);

namespace EricGansa\FilterManagerBundle\Security;

use EricGansa\FilterManagerBundle\Contract\SecurityUserResolverInterface;

/**
 * Fallback resolver used when Symfony SecurityBundle is NOT installed.
 *
 * Always returns null, effectively disabling user-scoped filtering.
 * This allows the bundle to work in projects without authentication.
 */
final class NullSecurityUserResolver implements SecurityUserResolverInterface
{
    public function getCurrentUser(): ?object
    {
        return null;
    }
}
