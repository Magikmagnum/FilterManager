<?php

declare(strict_types=1);

namespace EricGansa\FilterManagerBundle\Tests\Security;

use EricGansa\FilterManagerBundle\Security\SecurityUserResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @covers \EricGansa\FilterManagerBundle\Security\SecurityUserResolver
 */
class SecurityUserResolverTest extends TestCase
{
    public function testGetCurrentUserDelegatesToSecurity(): void
    {
        $user     = $this->createMock(UserInterface::class);
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $resolver = new SecurityUserResolver($security);

        $this->assertSame($user, $resolver->getCurrentUser());
    }

    public function testGetCurrentUserReturnsNullWhenNotAuthenticated(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $resolver = new SecurityUserResolver($security);

        $this->assertNull($resolver->getCurrentUser());
    }

    public function testImplementsInterface(): void
    {
        $security = $this->createMock(Security::class);
        $resolver = new SecurityUserResolver($security);

        $this->assertInstanceOf(
            \EricGansa\FilterManagerBundle\Contract\SecurityUserResolverInterface::class,
            $resolver
        );
    }
}
