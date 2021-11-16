<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

use HeimrichHannot\EntityApprovalBundle\DataContainer\EntityApprovalContainer;

$GLOBALS['TL_DCA'][\HeimrichHannot\EntityApprovalBundle\Model\EntityApprovalHistoryModel::getTable()] = [
    'config' => [
        'dataContainer' => 'Table',
        'ptable' => 'tl_submission',
        'enableVersioning' => false,
        'dynamicPtable' => true,
        'closed' => true,
        'notEditable' => false,
        'sql' => [
            'keys' => [
                'id' => 'primary',
            ],
        ],
    ],
    'list' => [
        'label' => [
            'fields' => ['state', 'transition', 'notes', 'auditor'],
            'format' => '%s',
            'showColumns' => true,
        ],
        'sorting' => [
            'mode' => 1,
            'fields' => ['dateAdded ASC'],
            'panelLayout' => 'filter;sort,search,limit',
        ],
        'global_operations' => [
        ],
        'operations' => [
        ],
    ],
    'palettes' => [
        '__selector__' => [],
        'default' => '{general_legend},state,transition,auditor,notes,informAuthor,confirmContinue',
    ],
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'dateAdded' => [
            'label' => &$GLOBALS['TL_LANG']['MSC']['dateAdded'],
            'sorting' => true,
            'flag' => 6,
            'eval' => ['rgxp' => 'datim', 'doNotCopy' => true],
            'save_callback' => [
                ['huh.utils.dca', 'setDateAdded'],
            ],
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'pid' => [
            'foreignKey' => 'tl_comments.id',
            'sql' => 'int(10) unsigned NOT NULL default 0',
            'relation' => ['type' => 'belongsTo', 'load' => 'lazy'],
        ],
        'ptable' => [
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'notes' => [
            'label' => &$GLOBALS['TL_LANG']['MSC']['approval_notes'],
            'inputType' => 'textarea',
            'eval' => [
                'mandatory' => false,
                'rows' => 10,
                'tl_class' => 'long clr',
                'class' => 'monospace',
                'rte' => 'tinyMCE',
                'helpwizard' => false,
            ],
            'attributes' => ['legend' => 'publish_legend'],
            'sql' => 'text NULL',
        ],
        'state' => [
            'label' => &$GLOBALS['TL_LANG']['MSC']['approval_state'],
            'inputType' => 'select',
            'options' => EntityApprovalContainer::APPROVAL_STATES,
            'reference' => &$GLOBALS['TL_LANG']['MSC']['approval_state'],
            'eval' => [
                'tl_class' => 'w50',
                'readonly' => true,
            ],
            'sql' => "varchar(32) NOT NULL default ''",
        ],
        'transition' => [
            'label' => &$GLOBALS['TL_LANG']['MSC']['approval_transition'],
            'inputType' => 'select',
            'eval' => ['tl_class' => 'w50'],
            'options_callback' => [EntityApprovalContainer::class, 'getAvailableTransitions'],
            'reference' => $GLOBALS['TL_LANG']['MSC']['reference'],
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'auditor' => [
            'label' => &$GLOBALS['TL_LANG']['MSC']['approval_auditor'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['tl_class' => 'clr w50', 'chosen' => true, 'multiple' => true],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'informAuthor' => [
            'label' => &$GLOBALS['TL_LANG']['MSC']['approval_informAuthor'],
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'clr w50'],
            'sql' => "char(1) NOT NULL default ''",
        ],
        'confirmContinue' => [
            'label' => &$GLOBALS['TL_LANG']['MSC']['approval_confirmContinue'],
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'clr w50'],
            'sql' => "char(1) NOT NULL default ''",
        ],
    ],
];
