<?php

declare(strict_types=1);

namespace EricGansa\FilterManagerBundle\Tests\Security;

use EricGansa\FilterManagerBundle\Security\NullSecurityUserResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullSecurityUserResolver::class)]
class NullSecurityUserResolverTest extends TestCase
{
    public function testGetCurrentUserAlwaysReturnsNull(): void
    {
        $resolver = new NullSecurityUserResolver();

        $this->assertNull($resolver->getCurrentUser());
    }

    public function testImplementsInterface(): void
    {
        $resolver = new NullSecurityUserResolver();

        $this->assertInstanceOf(
            \EricGansa\FilterManagerBundle\Contract\SecurityUserResolverInterface::class,
            $resolver
        );
    }
}
