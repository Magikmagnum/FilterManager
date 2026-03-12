<?php

declare(strict_types=1);

namespace EricGansa\FilterManagerBundle\Tests\DependencyInjection;

use EricGansa\FilterManagerBundle\Contract\SecurityUserResolverInterface;
use EricGansa\FilterManagerBundle\DependencyInjection\FilterManagerExtension;
use EricGansa\FilterManagerBundle\Filter\FilterManager;
use EricGansa\FilterManagerBundle\Security\NullSecurityUserResolver;
use EricGansa\FilterManagerBundle\Security\SecurityUserResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(FilterManagerExtension::class)]
class FilterManagerExtensionTest extends TestCase
{
    private FilterManagerExtension $extension;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new FilterManagerExtension();
        $this->container = new ContainerBuilder();
    }

    public function testGetAlias(): void
    {
        $this->assertSame('filter_manager', $this->extension->getAlias());
    }

    public function testLoadRegistersFilterManagerService(): void
    {
        $this->extension->load([], $this->container);

        $this->assertTrue($this->container->hasDefinition(FilterManager::class));
    }

    public function testLoadWithDefaultConfigInjectsMaxLimit(): void
    {
        $this->extension->load([], $this->container);

        $definition = $this->container->getDefinition(FilterManager::class);

        $this->assertSame(100, $definition->getArgument('$maxLimit'));
    }

    public function testLoadWithCustomMaxLimit(): void
    {
        $this->extension->load([['max_limit' => 50]], $this->container);

        $definition = $this->container->getDefinition(FilterManager::class);

        $this->assertSame(50, $definition->getArgument('$maxLimit'));
    }

    public function testLoadWithDefaultConfigInjectsScopeField(): void
    {
        $this->extension->load([], $this->container);

        $definition = $this->container->getDefinition(FilterManager::class);

        $this->assertSame('user', $definition->getArgument('$scopeField'));
    }

    public function testLoadWithCustomScopeField(): void
    {
        $this->extension->load([['scope_field' => 'owner']], $this->container);

        $definition = $this->container->getDefinition(FilterManager::class);

        $this->assertSame('owner', $definition->getArgument('$scopeField'));
    }

    public function testLoadWithDefaultConfigInjectsScopes(): void
    {
        $this->extension->load([], $this->container);

        $definition = $this->container->getDefinition(FilterManager::class);
        $scopes     = $definition->getArgument('$scopes');

        $this->assertSame('mine', $scopes['mine']);
        $this->assertSame('others', $scopes['others']);
        $this->assertSame('all', $scopes['all']);
    }

    public function testLoadRegistersSecurityUserResolverInterface(): void
    {
        $this->extension->load([], $this->container);

        $this->assertTrue($this->container->hasDefinition(SecurityUserResolverInterface::class));
    }

    public function testLoadWithSecurityBundleRegistersSecurityResolver(): void
    {
        $this->extension->load([], $this->container);

        $definition = $this->container->getDefinition(SecurityUserResolverInterface::class);

        $this->assertSame(SecurityUserResolver::class, $definition->getClass());
    }

    public function testLoadWithoutSecurityBundleRegistersNullResolver(): void
    {
        $extension = new class extends FilterManagerExtension {
            protected function hasSecurityBundle(): bool
            {
                return false;
            }
        };

        $container = new ContainerBuilder();
        $extension->load([], $container);

        $definition = $container->getDefinition(SecurityUserResolverInterface::class);

        $this->assertSame(NullSecurityUserResolver::class, $definition->getClass());
    }

    public function testHasSecurityBundleReturnsBool(): void
    {
        $result = (new \ReflectionMethod(FilterManagerExtension::class, 'hasSecurityBundle'))
            ->invoke($this->extension);

        $this->assertIsBool($result);
    }
}
