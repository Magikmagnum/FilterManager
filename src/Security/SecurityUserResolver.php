<?php

declare(strict_types=1);

namespace EricGansa\FilterManagerBundle\Security;

use EricGansa\FilterManagerBundle\Contract\SecurityUserResolverInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Resolves the current user via Symfony SecurityBundle.
 *
 * This class is only registered when SecurityBundle is installed.
 * @see NullSecurityUserResolver for the fallback implementation.
 */
final class SecurityUserResolver implements SecurityUserResolverInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public function getCurrentUser(): ?object
    {
        return $this->security->getUser();
    }
}
