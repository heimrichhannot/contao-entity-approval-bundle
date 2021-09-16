<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovementBundle\Manager;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use HeimrichHannot\EntityApprovementBundle\DataContainer\EntityApprovementContainer;
use HeimrichHannot\UtilsBundle\Dca\DcaUtil;

class EntityApprovementManager
{
    /**
     * @var array
     */
    protected $bundleConfig;
    /**
     * @var DcaUtil
     */
    protected $dcaUtil;

    public function __construct(DcaUtil $dcaUtil, array $bundleConfig)
    {
        $this->bundleConfig = $bundleConfig;
        $this->dcaUtil = $dcaUtil;
    }

    public function addApprovementToDca(string $table): void
    {
        $this->dcaUtil->loadDc($table);

        $this->addApprovementFieldsToDca($table);

        if (!$this->bundleConfig[$table]['exclude_from_palettes']) {
            $fieldManipulator = PaletteManipulator::create()
                ->addField(['huhApprovement_auditor', 'huhApprovement_state', 'huhApprovement_notes', 'huhApprovement_informAuthor'], 'approvement_legend', PaletteManipulator::POSITION_APPEND);
        }

        $legendManipulator = PaletteManipulator::create()
            ->addLegend('approvement_legend', 'publish_legend', PaletteManipulator::POSITION_PREPEND);

        foreach ($GLOBALS['TL_DCA'][$table]['palettes'] as $key => $palette) {
            if ('__selector__' === $key) {
                continue;
            }

            $legendManipulator->applyToPalette($key, $table);

            if (!$this->bundleConfig[$table]['exclude_from_palettes']) {
                $fieldManipulator->applyToPalette($key, $table);
            }
        }
    }

    private function addApprovementFieldsToDca(string $table): void
    {
        $dca = &$GLOBALS['TL_DCA'][$table];

        $fields = [
            'huhApprovement_auditor' => [
                'label' => &$GLOBALS['TL_LANG']['MSC']['approvement_auditor'],
                'exclude' => true,
                'search' => true,
                'sorting' => true,
                'inputType' => 'checkbox',
                'options_callback' => [EntityApprovementContainer::class, 'getAuditors'],
                'eval' => ['multiple' => true, 'mandatory' => false, 'tl_class' => 'clr w50'],
                'attributes' => ['legend' => 'publish_legend', 'fe_sorting' => true, 'fe_search' => true],
                'sql' => 'blob NULL',
            ],
            'huhApprovement_state' => [
                'label' => &$GLOBALS['TL_LANG']['MSC']['approvement_state'],
                'exclude' => true,
                'search' => true,
                'sorting' => true,
                'inputType' => 'radio',
                'options' => EntityApprovementContainer::APPROVEMENT_STATES,
                'reference' => &$GLOBALS['TL_LANG']['MSC']['approvement_state'],
                'eval' => ['mandatory' => false, 'tl_class' => 'w50'],
                'attributes' => ['legend' => 'publish_legend', 'fe_sorting' => true, 'fe_search' => true],
                'sql' => 'blob NULL',
            ],
            'huhApprovement_notes' => [
                'label' => &$GLOBALS['TL_LANG']['MSC']['approvement_notes'],
                'exclude' => true,
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
            'huhApprovement_informAuthor' => [
                'label' => &$GLOBALS['TL_LANG']['MSC']['approvement_informAuthor'],
                'exclude' => true,
                'sorting' => true,
                'inputType' => 'checkbox',
                'eval' => [
                    'mandatory' => false,
                    'tl_class' => 'clr w50',
                ],
                'attributes' => ['legend' => 'publish_legend', 'fe_sorting' => true],
                'sql' => "char(1) NOT NULL default ''",
            ],
        ];

        $dca['fields'] = array_merge(\is_array($dca['fields']) ? $dca['fields'] : [], $fields);
    }
}