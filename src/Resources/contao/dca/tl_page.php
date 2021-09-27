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
//    'entityApprovementConfig' => [
//        'label'                   => &$GLOBALS['TL_LANG']['tl_page']['entityApprovementConfig'],
//        'exclude'                 => true,
//        'filter'                  => true,
//        'inputType'               => 'select',
//        'options' => [],
//        'reference' => &$GLOBALS['TL_LANG']['tl_']['reference'],
//        'options_callback' => [PageContainer::class, 'getEntityApprovementConfigs'],
//        'eval'                    => ['tl_class' => 'w50', 'mandatory' => true, 'includeBlankOption' => true, 'submitOnChange' => true],
//        'sql'                     => "varchar(64) NOT NULL default ''"
//    ],
    'entityApprovementConfig' => [
        'inputType' => 'group',
        'storage' => 'entity',
        'entity' => EntityApprovementConfig::class,
        'min' => 1,
        'palette' => [
            'entity',
            'initial_auditor_groups',
            'initial_auditor_mode',
            'auditor_groups',
        ],
        'fields' => [
            'entity' => [
                'label' => &$GLOBALS['TL_LANG']['tl_page']['entityApprovement'],
                'exclude' => true,
                'filter' => true,
                'search' => true,
                'inputType' => 'select',
                'options' => [],
                'reference' => &$GLOBALS['TL_LANG']['tl_']['reference'],
                'options_callback' => [PageContainer::class, 'getAllEntities'],
                'default' => '0',
                'eval' => [
                    'chosen' => true,
                    'tl_class' => 'w50',
                    'mandatory' => true,
                    'includeBlankOption' => true,
                    'sql' => '',
                ],
            ],
            'initial_auditor_groups' => [
                'label' => &$GLOBALS['TL_LANG']['tl_page']['entityApprovement']['inialAuditorGroups'],
                'exclude' => true,
                'inputType' => 'checkbox',
                'options_callback' => [PageContainer::class, 'getInitialAuditorGroups'],
                'eval' => [
                    'tl_class' => 'w50',
                ],
            ],
            'initial_auditor_mode' => [
                'label' => &$GLOBALS['TL_LANG']['tl_page']['entityApprovment']['initialAuditorMode'],
                'exclude' => true,
                'filter' => true,
                'inputType' => 'select',
                'options' => [],
                'reference' => &$GLOBALS['TL_LANG']['tl_']['reference'],
                'options_callback' => [PageContainer::class, 'getInitialAuditorModes'],
                'eval' => [
                    'tl_class' => 'w50',
                    'mandatory' => true,
                    'includeBlankOption' => true,
                ],
            ],
            'auditor_groups' => [
                'label' => &$GLOBALS['TL_LANG']['tl_page']['entityApprovement']['auditor'],
                'exclude' => true,
                'filter' => true,
                'inputType' => 'select',
                'options' => [],
                'reference' => &$GLOBALS['TL_LANG']['tl_page']['reference'],
                'options_callback' => [PageContainer::class, 'getAuditorGroups'],
                'eval' => [
                    'tl_class' => 'w50',
                    'groupStyle' => 'width: 48%',
                    'mandatory' => true,
                    'includeBlankOption' => true,
                ],
            ],
        ],
    ],
//    'entityApprovement' => [
//        'label' => &$GLOBALS['TL_LANG']['tl_page']['entityApprovement'],
//        'inputType' => 'multiColumnEditor',
//        'save_callback' => [[PageContainer::class, 'onSaveEntityApprovement']],
//        'eval' => [
//            'tl_class' => 'long clr',
//            'minRowCount' => 1,
//            'skipCopyValuesOnAdd' => true,
//            'multiColumnEditor' => [
//                'palettes' => [
//                    'default' => 'entity',
////                    'default' => 'entity,initialAuditorGroups,initialAuditorMode,auditor,publishField,invertPublishField,authorField,emails'
//                ],
//                'subpalettes' => [
//
//                ],
//                'fields' => [
//                    'entity' => [
//                        'label'                   => &$GLOBALS['TL_LANG']['tl_page']['entityApprovement'],
//                        'exclude'                 => true,
//                        'filter'                  => true,
//                        'search'                  => true,
//                        'inputType'               => 'select',
//                        'options' => [],
//                        'reference' => &$GLOBALS['TL_LANG']['tl_']['reference'],
//                        'options_callback' => [PageContainer::class, 'getAllEntities'],
//                        'eval'                    => [
//                            'chosen' => true,
//                            'tl_class' => 'w50',
//                            'mandatory' => true,
//                            'includeBlankOption' => true,
//                            'submitOnChange' => true
//                        ],
//                        'sql'                     => "varchar(64) NOT NULL default ''"
//                    ],
//                    'initialAuditorGroups' => [
//                        'label'                   => &$GLOBALS['TL_LANG']['tl_page']['entityApprovement']['inialAuditorGroups'],
//                        'exclude'                 => true,
//                        'inputType'               => 'checkbox',
//                        'options_callback' => [PageContainer::class, 'getInitialAuditorGroups'],
//                        'eval'                    => ['tl_class' => 'w50'],
//                        'sql'                     => "char(1) NOT NULL default ''"
//                    ],
//                    'initialAuditorMode' => [
//                        'label'                   => &$GLOBALS['TL_LANG']['tl_page']['entityApprovment']['initialAuditorMode'],
//                        'exclude'                 => true,
//                        'filter'                  => true,
//                        'inputType'               => 'select',
//                        'options' => [],
//                        'reference' => &$GLOBALS['TL_LANG']['tl_']['reference'],
//                        'options_callback' => [PageContainer::class, 'getInitialAuditorModes'],
//                        'eval'                    => ['tl_class' => 'w50', 'mandatory' => true, 'includeBlankOption' => true, 'submitOnChange' => true],
//                    ],
//                    'publishField' => [
//                        'label'                   => &$GLOBALS['TL_LANG']['tl_page']['entityApprovement']['publishField'],
//                        'exclude'                 => true,
//                        'filter'                  => true,
//                        'inputType'               => 'select',
//                        'options' => [],
//                        'reference' => &$GLOBALS['TL_LANG']['tl_']['reference'],
//                        'options_callback' => [PageContainer::class, 'getAllFields'],
//                        'eval'                    => ['tl_class' => 'w50', 'mandatory' => true, 'includeBlankOption' => true, 'submitOnChange' => true],
//                    ],
//                    'invertPublishField' => [
//                        'label'                   => &$GLOBALS['TL_LANG']['tl_entityApprovement']['inverPublishField'],
//                        'exclude'                 => true,
//                        'inputType'               => 'checkbox',
//                        'eval'                    => ['tl_class' => 'w50'],
//                    ],
//                    'authorField' => [
//                        'label'                   => &$GLOBALS['TL_LANG']['tl_page']['entityApprovement']['authorField'],
//                        'exclude'                 => true,
//                        'filter'                  => true,
//                        'inputType'               => 'select',
//                        'options' => [],
//                        'reference' => &$GLOBALS['TL_LANG']['tl_']['reference'],
//                        'options_callback' => [PageContainer::class, 'getAllFields'],
//                        'eval'                    => ['tl_class' => 'w50', 'mandatory' => true, 'includeBlankOption' => true, 'submitOnChange' => true],
//                    ],
//                    'emails' => [
//                        'label'                   => &$GLOBALS['TL_LANG']['tl_page']['entityApprovement']['emails'],
//                        'exclude'                 => true,
//                        'inputType'               => 'checkbox',
//                        'eval'                    => ['tl_class' => 'w50', 'multiple' => true, 'includeBlankOption' => true],
//                    ],
//                    'auditor' => [
//                        'label' => &$GLOBALS['TL_LANG']['tl_page']['entityApprovement']['auditor'],
//                        'exclude' => true,
//                        'filter' => true,
//                        'inputType' => 'select',
//                        'options' => [],
//                        'reference' => &$GLOBALS['TL_LANG']['tl_page']['reference'],
//                        'options_callback' => [PageContainer::class, 'getAuditorGroups'],
//                        'eval' => ['tl_class' => 'w50', 'groupStyle' => 'width: 48%', 'mandatory' => true, 'includeBlankOption' => true, 'submitOnChange' => false],
//                    ],
//                ],
//            ],
//        ],
//        'sql' => 'blob NULL',
//    ]
];

$dca['fields'] = array_merge(is_array($dca['fields']) ? $dca['fields'] : [], $fields);
