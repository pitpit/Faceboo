<?php

namespace Faceboo\FacebookBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 * 
 * @author Damien Pitard <dpitard at digitas.fr>
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('faceboo_facebook');

        $rootNode
        ->children()
            ->scalarNode('app_id')->isRequired()->end()
            ->scalarNode('secret')->isRequired()->end()
            ->scalarNode('canvas')->defaultValue(true)->end()
            ->scalarNode('namespace')->end()
            ->scalarNode('connect_timeout')->end()
            ->scalarNode('timeout')->end()
            ->scalarNode('proxy')->end()
            ->arrayNode('permissions')->end()
            ->arrayNode('redirect')->end()
        ->end();
        
        // Here you should define the parameters that are allowed to
        // configure your bundle. See the documentation linked above for
        // more information on that topic.

        return $treeBuilder;
    }
}
