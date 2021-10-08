<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

$GLOBALS['TL_DCA'][\HeimrichHannot\EntityApprovalBundle\Model\EntityApprovalHistoryModel::getTable()] = [
    'config' => [
        'dataContainer' => 'Table',
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
            'fields' => ['author', 'state', 'transition', 'notes', 'auditor'],
            'headerFields' => ['author', 'state', 'transition', 'notes', 'auditor'],
            'format' => '%s',
            'showColumns' => true,
        ],
        'sorting' => [
            'mode' => 0,
            'fields' => ['dateAdded'],
            'panelLayout' => 'filter;sort,search,limit',
        ],
        'global_operations' => [
        ],
        'operations' => [
            'edit' => [
                'label' => &$GLOBALS['TL_LANG']['tl_draft_archive_action']['edit'],
                'href' => 'act=edit',
                'icon' => 'edit.gif',
            ],
            'delete' => [
                'label' => &$GLOBALS['TL_LANG']['tl_comments_history']['delete'],
                'href' => 'act=delete',
                'icon' => 'delete.gif',
                'attributes' => 'onclick="if(!confirm(\''.$GLOBALS['TL_LANG']['MSC']['deleteConfirm'].'\'))return false;Backend.getScrollOffset()"',
            ],
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
            'inputType' => 'text',
            'eval' => ['tl_class' => 'w50'],
            'sql' => "varchar(32) NOT NULL default ''",
        ],
        'transition' => [
            'label' => &$GLOBALS['TL_LANG']['MSC']['approval_transition'],
            'inputType' => 'text',
            'eval' => ['tl_class' => 'w50'],
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'auditor' => [
            'label' => &$GLOBALS['TL_LANG']['MSC']['approval_auditor'],
            'inputType' => 'text',
            'eval' => ['tl_class' => 'clr w50'],
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
