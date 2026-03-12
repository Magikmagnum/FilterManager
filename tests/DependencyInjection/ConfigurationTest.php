<?php

declare(strict_types=1);

namespace EricGansa\FilterManagerBundle\Tests\DependencyInjection;

use EricGansa\FilterManagerBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

/**
 * @covers \EricGansa\FilterManagerBundle\DependencyInjection\Configuration
 */
class ConfigurationTest extends TestCase
{
    private Processor $processor;
    private Configuration $configuration;

    protected function setUp(): void
    {
        $this->processor     = new Processor();
        $this->configuration = new Configuration();
    }

    public function testDefaultConfiguration(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, []);

        $this->assertSame(100, $config['max_limit']);
        $this->assertSame('user', $config['scope_field']);
        $this->assertSame('mine', $config['scopes']['mine']);
        $this->assertSame('others', $config['scopes']['others']);
        $this->assertSame('all', $config['scopes']['all']);
    }

    public function testCustomMaxLimit(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [
            ['max_limit' => 250],
        ]);

        $this->assertSame(250, $config['max_limit']);
    }

    public function testCustomScopeField(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [
            ['scope_field' => 'owner'],
        ]);

        $this->assertSame('owner', $config['scope_field']);
    }

    public function testCustomScopeNames(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [
            ['scopes' => ['mine' => 'personal', 'others' => 'community', 'all' => 'everything']],
        ]);

        $this->assertSame('personal', $config['scopes']['mine']);
        $this->assertSame('community', $config['scopes']['others']);
        $this->assertSame('everything', $config['scopes']['all']);
    }

    public function testMaxLimitBelowOneThrowsException(): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);

        $this->processor->processConfiguration($this->configuration, [
            ['max_limit' => 0],
        ]);
    }

    public function testConfigurationTreeBuilderReturnsCorrectRootName(): void
    {
        $treeBuilder = $this->configuration->getConfigTreeBuilder();

        $this->assertSame('filter_manager', $treeBuilder->buildTree()->getName());
    }
}
