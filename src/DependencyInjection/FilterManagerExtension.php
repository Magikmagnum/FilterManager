<?php

declare(strict_types=1);

namespace EricGansa\FilterManagerBundle\DependencyInjection;

use EricGansa\FilterManagerBundle\Contract\SecurityUserResolverInterface;
use EricGansa\FilterManagerBundle\Filter\FilterManager;
use EricGansa\FilterManagerBundle\Security\NullSecurityUserResolver;
use EricGansa\FilterManagerBundle\Security\SecurityUserResolver;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Loads and manages FilterManagerBundle configuration and service definitions.
 *
 * Automatically wires SecurityUserResolver when SecurityBundle is installed,
 * and falls back to NullSecurityUserResolver otherwise.
 */
class FilterManagerExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config        = $this->processConfiguration($configuration, $configs);

        // Load base service definitions
        $loader = new YamlFileLoader($container, new FileLocator(\dirname(__DIR__, 2) . '/config'));
        $loader->load('services.yaml');

        // Register the appropriate SecurityUserResolver based on bundle availability
        // This is the mechanism that makes Security OPTIONAL (Improvement 5.1)
        if (class_exists(\Symfony\Bundle\SecurityBundle\Security::class)) {
            $container->register(SecurityUserResolverInterface::class, SecurityUserResolver::class)
                ->setArgument('$security', new Reference(\Symfony\Bundle\SecurityBundle\Security::class))
                ->setPublic(false)
                ->setAutowired(false);
        } else {
            $container->register(SecurityUserResolverInterface::class, NullSecurityUserResolver::class)
                ->setPublic(false)
                ->setAutowired(false);
        }

        // Inject bundle configuration values into FilterManager (Improvements 5.2 and 5.3)
        $container->getDefinition(FilterManager::class)
            ->setArgument('$userResolver', new Reference(SecurityUserResolverInterface::class))
            ->setArgument('$maxLimit', $config['max_limit'])
            ->setArgument('$scopes', $config['scopes'])
            ->setArgument('$scopeField', $config['scope_field']);
    }

    public function getAlias(): string
    {
        return 'filter_manager';
    }
}
