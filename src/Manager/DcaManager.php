<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovalBundle\Manager;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use HeimrichHannot\EntityApprovalBundle\DataContainer\EntityApprovalContainer;
use HeimrichHannot\UtilsBundle\Dca\DcaUtil;

class DcaManager
{
    protected array             $bundleConfig;
    protected DcaUtil           $dcaUtil;

    public function __construct(DcaUtil $dcaUtil, array $bundleConfig)
    {
        $this->bundleConfig = $bundleConfig;
        $this->dcaUtil = $dcaUtil;
    }

    public function addApprovalToDca(string $table): void
    {
        $this->dcaUtil->loadDc($table);

        $this->addApprovalFieldsToDca($table);

        if (!$this->bundleConfig[$table]['exclude_from_palettes']) {
            $fieldManipulator = PaletteManipulator::create()
                ->addField(['huhApproval_auditor', 'huhApproval_state', 'huhApproval_notes', 'huhApproval_informAuthor'], 'approval_legend', PaletteManipulator::POSITION_APPEND);
        }

        $legendManipulator = PaletteManipulator::create()
            ->addLegend('approval_legend', 'publish_legend', PaletteManipulator::POSITION_PREPEND);

        foreach ($GLOBALS['TL_DCA'][$table]['palettes'] as $key => $palette) {
            if ('__selector__' === $key) {
                continue;
            }

            $legendManipulator->applyToPalette($key, $table);

            if (!$this->bundleConfig[$table]['exclude_from_palettes']) {
                $fieldManipulator->applyToPalette($key, $table);
            }
        }

        $dca = &$GLOBALS['TL_DCA'][$table];
        $dca['config']['oncreate_callback'][] = [EntityApprovalContainer::class, 'onCreate'];
//        $dca['config']['onsubmit_callback'][] = [EntityApprovalContainer::class, 'onSubmit'];
        $dca['fields'][$this->bundleConfig[$table]['publish_field']]['save_callback'] = [[EntityApprovalContainer::class, 'onPublish']];
    }

    public function addApprovalConfigToPage(): void
    {
        $dca = &$GLOBALS['TL_DCA']['tl_page'];

        $dca['subpalettes']['activateEntityApproval'] = 'entityApprovalConfig';
        $dca['palettes']['__selector__'][] = 'activateEntityApproval';

        foreach ($dca['palettes'] as $paletteName => $palette) {
            if (!\is_string($palette)) {
                continue;
            }

            switch ($paletteName) {
                case 'root':
                case 'rootfallback':
                    PaletteManipulator::create()
                        ->addLegend('entity_approval_legend', 'publish_legend', PaletteManipulator::POSITION_BEFORE)
                        ->addField(['activateEntityApproval'], 'entity_approval_legend', PaletteManipulator::POSITION_APPEND)
                        ->applyToPalette($paletteName, 'tl_page');

                    break;

                case 'regular':
                    $this->dcaUtil->addOverridableFields(['activateEntityApproval'], 'tl_page', 'tl_page');

                    PaletteManipulator::create()
                        ->addLegend('entity_approval_legend', 'publish_legend', PaletteManipulator::POSITION_BEFORE)
                        ->addField(['overrideActivateEntityApproval'], 'entity_approval_legend', PaletteManipulator::POSITION_APPEND)
                        ->applyToPalette($paletteName, 'tl_page');

                    break;

                default:
                    break;
            }
        }
    }

    private function addApprovalFieldsToDca(string $table): void
    {
        $dca = &$GLOBALS['TL_DCA'][$table];

        $fields = [
            'huhApproval_auditor' => [
                'label' => &$GLOBALS['TL_LANG']['MSC']['approval_auditor'],
                'exclude' => true,
                'search' => true,
                'sorting' => true,
                'inputType' => 'checkbox',
                'options_callback' => [EntityApprovalContainer::class, 'getAuditors'],
                'eval' => ['multiple' => true, 'mandatory' => false, 'tl_class' => 'clr w50'],
                'attributes' => ['legend' => 'publish_legend', 'fe_sorting' => true, 'fe_search' => true],
                'sql' => 'blob NULL',
            ],
            'huhApproval_state' => [
                'label' => &$GLOBALS['TL_LANG']['MSC']['approval_state'],
                'exclude' => true,
                'search' => false,
                'sorting' => false,
                'inputType' => 'radio',
                'options' => EntityApprovalContainer::APPROVAL_STATES,
                'reference' => &$GLOBALS['TL_LANG']['MSC']['approval_state'],
                'eval' => [
                    'mandatory' => false,
                    'tl_class' => 'w50',
                    'readonly' => true,
                ],
                'attributes' => ['legend' => 'publish_legend', 'fe_sorting' => true, 'fe_search' => true],
                'sql' => "varchar(32) NOT NULL default '".EntityApprovalContainer::APPROVAL_STATE_CREATED."'",
            ],
            'huhApproval_notes' => [
                'label' => &$GLOBALS['TL_LANG']['MSC']['approval_notes'],
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
            'huhApproval_informAuthor' => [
                'label' => &$GLOBALS['TL_LANG']['MSC']['approval_informAuthor'],
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
