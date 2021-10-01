<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovalBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    const ROOT_ID = 'huh_entity_approval';

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
                    ->arrayNode('auditor_levels')
                        ->info('Add as many levels as needed')
                        ->arrayPrototype()
                            ->children()
                                ->scalarNode('name')
                                    ->info('This will be displayed in history, on approval changes.')
                                    ->isRequired()
                                ->end()
                                ->arrayNode('groups')
                                    ->info('This will be usergroups auditor will be chosen.')
                                    ->isRequired()
                                    ->scalarPrototype()
                                    ->end()
                                ->end()
                                ->scalarNode('mode')
                                    ->info('This is the mode how auditors will be chosen.')
                                    ->isRequired()
                                    ->defaultValue('random')
                                ->end()
                            ->end()
                        ->end()
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
