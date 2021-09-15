<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovementBundle\Manager;

use Contao\CoreBundle\DataContainer\PaletteManipulator;

class EntityApprovementManager
{
    /**
     * @var array
     */
    protected $bundleConfig;

    public function __construct(array $bundleConfig)
    {
        $this->bundleConfig = $bundleConfig;
    }

    public function addApprovementToDca(string $table): void
    {
        $this->addApprovementFieldsToDca($table);

        PaletteManipulator::create()->addField(['auditor', 'state', 'notes', 'informAuthor'], 'publish_legend', PaletteManipulator::POSITION_PREPEND)->applyToPalette('default', $table);
    }

    private function addApprovementFieldsToDca(string $table): void
    {
        $dca = &$GLOBALS['TL_DCA'][$table];

        $fields = [
            'auditor' => [
                'label' => &$GLOBALS['TL_LANG']['MSC']['approvement_author'],
                'exclude' => true,
                'search' => true,
                'sorting' => true,
                'inputType' => 'checkbox',
                'options' => ['1', '2', '4'],
                'eval' => ['multiple' => true, 'mandatory' => false, 'tl_class' => 'clr w50'],
                'attributes' => ['legend' => 'publish_legend', 'fe_sorting' => true, 'fe_search' => true],
                'sql' => 'blob NULL',
            ],
            'state' => [
                'label' => &$GLOBALS['TL_LANG']['MSC']['approvement_state'],
                'exclude' => true,
                'search' => true,
                'sorting' => true,
                'inputType' => 'radio',
                'options' => ['1', '2', '4'],
                'eval' => ['mandatory' => false, 'tl_class' => 'clr w50'],
                'attributes' => ['legend' => 'publish_legend', 'fe_sorting' => true, 'fe_search' => true],
                'sql' => 'blob NULL',
            ],
            'notes' => [
                'label' => &$GLOBALS['TL_LANG']['MSC']['approvement_notes'],
                'exclude' => true,
                'inputType' => 'textarea',
                'eval' => ['mandatory' => false],
                'attributes' => ['legend' => 'publish_legend'],
                'sql' => 'text NULL',
            ],
            'informAuthor' => [
                'label' => &$GLOBALS['TL_LANG']['MSC']['approvement_inform_author'],
                'exclude' => true,
                'sorting' => true,
                'inputType' => 'checkbox',
                'eval' => ['mandatory' => false, 'tl_class' => 'clr w50'],
                'attributes' => ['legend' => 'publish_legend', 'fe_sorting' => true],
                'sql' => "char(1) NOT NULL default ''",
            ],
        ];

        $dca['fields'] = array_merge(\is_array($dca['fields']) ? $dca['fields'] : [], $fields);
    }
}
