<?php

declare(strict_types=1);

namespace EricGansa\FilterManagerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Defines the configuration tree for the FilterManagerBundle.
 *
 * Example configuration (config/packages/filter_manager.yaml):
 *
 *   filter_manager:
 *       max_limit: 100
 *       scope_field: user
 *       scopes:
 *           mine: 'mine'
 *           others: 'others'
 *           all: 'all'
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('filter_manager');
        $rootNode    = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->integerNode('max_limit')
                    ->defaultValue(100)
                    ->min(1)
                    ->info('Maximum number of items per page. Prevents abuse like ?limit=99999. Default: 100.')
                ->end()
                ->scalarNode('scope_field')
                    ->defaultValue('user')
                    ->cannotBeEmpty()
                    ->info('Entity field name linking to the owner user. Used for mine/others scope filtering. Default: "user".')
                ->end()
                ->arrayNode('scopes')
                    ->addDefaultsIfNotSet()
                    ->info('Configurable scope names used in the ?scope=X query parameter.')
                    ->children()
                        ->scalarNode('mine')
                            ->defaultValue('mine')
                            ->cannotBeEmpty()
                            ->info('Query string value that filters to only the current user\'s items. Default: "mine".')
                        ->end()
                        ->scalarNode('others')
                            ->defaultValue('others')
                            ->cannotBeEmpty()
                            ->info('Query string value that excludes the current user\'s items. Default: "others".')
                        ->end()
                        ->scalarNode('all')
                            ->defaultValue('all')
                            ->cannotBeEmpty()
                            ->info('Query string value that applies no user filter. Default: "all".')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
