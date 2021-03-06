<?php

namespace FM\Payment\AuthorizenetBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('fm_payment_authorizenet');

        $methods = array('checkout');

        $rootNode
            ->children()
                ->scalarNode('processes_type')
                    ->defaultValue('authorizenet_checkout')
                ->end()
                ->booleanNode('logger')
                    ->defaultTrue()
                ->end()
            ->end()

            ->fixXmlConfig('instance')
            ->children()
                ->arrayNode('instances')
                    ->defaultValue([])
                    ->prototype('array')
                        ->children()
                            ->scalarNode('processes_type')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()

            ->fixXmlConfig('method')
            ->children()
                ->arrayNode('methods')
                    ->defaultValue($methods)
                    ->prototype('scalar')
                        ->validate()
                            ->ifNotInArray($methods)
                            ->thenInvalid('%s is not a valid method.')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
