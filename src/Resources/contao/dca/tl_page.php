<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

use HeimrichHannot\EntityApprovementBundle\DataContainer\PageContainer;
use HeimrichHannot\EntityApprovementBundle\Entity\EntityApprovementConfig;

$dca = &$GLOBALS['TL_DCA']['tl_page'];

$fields = [
    'activateEntityApprovement' => [
        'label' => &$GLOBALS['TL_LANG']['tl_page']['activateEntityApprovement'],
        'exclude' => true,
        'inputType' => 'checkbox',
        'eval' => ['tl_class' => 'w50', 'submitOnChange' => true],
        'sql' => "char(1) NOT NULL default ''",
    ],
    'entityApprovementConfig' => [
        'inputType' => 'group',
        'storage' => 'entity',
        'entity' => EntityApprovementConfig::class,
        'min' => 1,
        'palette' => [
            'entityName',
            'initialAuditorGroups',
            'initialAuditorMode',
            'auditorGroups',
        ],
        'fields' => [
            'entityName' => [
                'label' => &$GLOBALS['TL_LANG']['tl_page']['entityApprovement']['entityName'],
                'inputType' => 'select',
                'options' => [],
                'reference' => &$GLOBALS['TL_LANG']['tl_page']['reference'],
                'options_callback' => [PageContainer::class, 'getAllEntities'],
                'eval' => [
                    'chosen' => true,
                    'tl_class' => 'w50',
                    'mandatory' => true,
                    'includeBlankOption' => true,
                ],
            ],
            'initialAuditorGroups' => [
                'label' => &$GLOBALS['TL_LANG']['tl_page']['entityApprovement']['initialAuditorGroups'],
                'inputType' => 'checkbox',
                'options_callback' => [PageContainer::class, 'getAuditorGroups'],
                'eval' => [
                    'tl_class' => 'w50',
                    'includeBlankOption' => true,
                    'multiple' => true,
                ],
            ],
            'initialAuditorMode' => [
                'label' => &$GLOBALS['TL_LANG']['tl_page']['entityApprovement']['initialAuditorMode'],
                'inputType' => 'select',
                'options' => [],
                'reference' => &$GLOBALS['TL_LANG']['tl_page']['reference'],
                'options_callback' => [PageContainer::class, 'getInitialAuditorModes'],
                'eval' => [
                    'tl_class' => 'w50',
                    'includeBlankOption' => true,
                ],
            ],
            'auditorGroups' => [
                'label' => &$GLOBALS['TL_LANG']['tl_page']['entityApprovement']['auditorGroups'],
                'inputType' => 'checkbox',
                'options' => [],
                'options_callback' => [PageContainer::class, 'getAuditorGroups'],
                'eval' => [
                    'tl_class' => 'w50',
                    'includeBlankOption' => true,
                    'multiple' => true,
                ],
            ],
        ],
    ],
];

$dca['fields'] = array_merge(is_array($dca['fields']) ? $dca['fields'] : [], $fields);
