<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovementBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    const ROOT_ID = 'huh_entity_approvement';

    const AUDITOR_MODE_ALL = 'all';
    const AUDITOR_MODE_RANDOM = 'random';

    const AUDITOR_MODES = [
        self::AUDITOR_MODE_ALL,
        self::AUDITOR_MODE_RANDOM,
    ];

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treebuilder = new TreeBuilder();
        $rootNode = $treebuilder->root(static::ROOT_ID);

        $rootNode
            ->arrayPrototype()
                ->children()
                    ->booleanNode('exclude_from_palettes')
                        ->info('Should the Fields be applied to DCA palettes')
                        ->defaultValue(false)
                    ->end()
                    ->scalarNode('initial_auditor_groups')
                        ->info('List of usergroups who are responsible to assign final approval usergroups.')
                        ->cannotBeEmpty()
                    ->end()
                    ->enumNode('initial_auditor_mode')
                        ->info('Mode how initial auditor should be chosen')
                        ->values(static::AUDITOR_MODES)
                        ->defaultValue(static::AUDITOR_MODE_ALL)
                    ->end()
                    ->scalarNode('auditor_groups')
                        ->info('List of usergroups that can be chosen as final approval group.')
                        ->cannotBeEmpty()
                    ->end()
                    ->scalarNode('publish_field')
                        ->info('Name of the field that is responsible for the publish state of the entity.')
                        ->defaultValue('published')
                    ->end()
                    ->booleanNode('invert_publish_field')
                        ->info('Invert the value of published field.')
                        ->defaultValue(false)
                    ->end()
                    ->scalarNode('author_field')
                        ->info('Name of the field that contains the author of the entity.')
                        ->defaultValue('author')
                    ->end()
                    ->arrayNode('emails')
                        ->children()
                            ->booleanNode('auditor_changed_former')
                                ->info('Should an email be sent to former auditor on auditor change?')
                                ->defaultValue(true)
                            ->end()
                            ->booleanNode('auditor_changed_new')
                                ->info('Should an email be sent to new auditor on auditor change?')
                                ->defaultValue(true)
                            ->end()
                            ->booleanNode('state_changed_author')
                                ->info('Should an email be sent to author on state change of the entity')
                                ->defaultValue(true)
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treebuilder;
    }
}
