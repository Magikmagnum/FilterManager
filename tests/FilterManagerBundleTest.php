<?php

declare(strict_types=1);

namespace EricGansa\FilterManagerBundle\Tests;

use EricGansa\FilterManagerBundle\DependencyInjection\FilterManagerExtension;
use EricGansa\FilterManagerBundle\FilterManagerBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FilterManagerBundle::class)]
class FilterManagerBundleTest extends TestCase
{
    public function testGetContainerExtensionReturnsFilterManagerExtension(): void
    {
        $bundle = new FilterManagerBundle();

        $this->assertInstanceOf(FilterManagerExtension::class, $bundle->getContainerExtension());
    }

    public function testGetContainerExtensionReturnsNewInstanceEachTime(): void
    {
        $bundle = new FilterManagerBundle();

        $this->assertNotSame($bundle->getContainerExtension(), $bundle->getContainerExtension());
    }
}
