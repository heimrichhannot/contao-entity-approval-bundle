<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovalBundle\Manager;

use Contao\BackendUser;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\Input;
use HeimrichHannot\EntityApprovalBundle\DataContainer\EntityApprovalContainer;
use HeimrichHannot\UtilsBundle\Dca\DcaUtil;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;
use HeimrichHannot\UtilsBundle\User\UserUtil;

class DcaManager
{
    protected array     $bundleConfig;
    protected DcaUtil   $dcaUtil;
    protected UserUtil  $userUtil;
    protected ModelUtil $modelUtil;

    public function __construct(DcaUtil $dcaUtil, UserUtil $userUtil, ModelUtil $modelUtil, array $bundleConfig)
    {
        $this->bundleConfig = $bundleConfig;
        $this->dcaUtil = $dcaUtil;
        $this->userUtil = $userUtil;
        $this->modelUtil = $modelUtil;
    }

    public function addApprovalToDca(string $table): void
    {
        $this->dcaUtil->loadDc($table);
        $this->addApprovalFieldsToDca($table);

        if (null === ($entity = $this->modelUtil->findModelInstanceByPk($table, Input::get('id')))) {
            return;
        }

        $author = $entity->row()[$this->bundleConfig[$table]['author_field']];

        $dca = &$GLOBALS['TL_DCA'][$table];

        $backendUser = BackendUser::getInstance();
//            unset($dca['fields']['huhApproval_state']);

        if (((int) $backendUser->id === (int) $author || (int) $backendUser->id !== (int) $entity->huhApproval_auditor) || EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT !== $entity->huhApproval_state) {
            unset($dca['fields']['huhApproval_notes'], $dca['fields']['huhApproval_informAuthor'], $dca['fields']['huhApproval_transition'], $dca['fields']['huhApproval_confirmContinue']);
        }
        $dca['config']['oncreate_callback'][] = [EntityApprovalContainer::class, 'onCreate'];
        $dca['config']['onsubmit_callback'][] = [EntityApprovalContainer::class, 'onSubmit'];
        $dca['fields'][$this->bundleConfig[$table]['publish_field']]['save_callback'] = [[EntityApprovalContainer::class, 'onPublish']];
    }

    private function addApprovalFieldsToDca(string $table): void
    {
        $dca = &$GLOBALS['TL_DCA'][$table];

        if (null === ($userModel = $this->modelUtil->findModelInstancesBy('tl_user', ['tl_user.username=?'], [$GLOBALS['TL_USERNAME']]))) {
            return;
        }

        $fields = [
            'huh_approval_state' => [
                'label' => &$GLOBALS['TL_LANG']['MSC']['approval_state'],
                'exclude' => true,
                'search' => false,
                'sorting' => false,
                'inputType' => 'select',
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
            'huh_approval_auditor' => [
                'label' => &$GLOBALS['TL_LANG']['MSC']['approval_auditor'],
                'exclude' => true,
                'search' => true,
                'sorting' => true,
                'inputType' => 'select',
                'options_callback' => [EntityApprovalContainer::class, 'getAuditors'],
                'save_callback' => [[EntityApprovalContainer::class, 'onSaveAuditor']],
                'eval' => ['multiple' => false, 'mandatory' => false, 'tl_class' => 'clr w50', 'includeBlankOption' => true],
                'attributes' => ['legend' => 'publish_legend', 'fe_sorting' => true, 'fe_search' => true],
                'sql' => 'blob NULL',
            ],
        ];

        foreach ($this->bundleConfig[$table]['auditor_levels'] as $level) {
            if (null === ($userGroups = $this->userUtil->getActiveGroups($userModel->id)) && !$this->userUtil->isAdmin($userModel->id)) {
                continue;
            }

            $fields['huh_approval_auditor_'.$level['name']] = [
                'label' => &$GLOBALS['TL_LANG']['MSC']['approval_auditor'],
                'exclude' => true,
                'search' => true,
                'sorting' => true,
                'inputType' => 'select',
                'options_callback' => [EntityApprovalContainer::class, 'getAuditors'],
                'save_callback' => [[EntityApprovalContainer::class, 'onSaveAuditor']],
                'eval' => ['multiple' => false, 'mandatory' => false, 'tl_class' => 'clr w50', 'includeBlankOption' => true],
                'attributes' => ['legend' => 'publish_legend', 'fe_sorting' => true, 'fe_search' => true],
                'sql' => 'blob NULL',
            ];

            $fields['huh_approval_notes_'.$level['name']] = [
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
            ];

            $fields['huh_approval_inform_author_'.$level['name']] = [
                'label' => &$GLOBALS['TL_LANG']['MSC']['approval_informAuthor'],
                'exclude' => true,
                'sorting' => true,
                'inputType' => 'checkbox',
                'eval' => [
                    'mandatory' => false,
                    'tl_class' => 'clr w50',
                ],
                'attributes' => ['legend' => 'publish_legend'],
                'sql' => "char(1) NOT NULL default ''",
            ];

            $fields['huh_approval_transition_'.$level['name']] = [
                'label' => &$GLOBALS['TL_LANG']['MSC']['approval_transition'],
                'filter' => true,
                'inputType' => 'select',
                'options_callback' => [EntityApprovalContainer::class, 'getAvailableTransitions'],
                'eval' => ['tl_class' => 'w50', 'mandatory' => true, 'includeBlankOption' => true],
                'sql' => "varchar(64) NOT NULL default ''",
            ];

            $fields['huh_approval_confirm_continue_'.$level['name']] = [
                'label' => &$GLOBALS['TL_LANG']['MSC']['approval_confirmContinue'],
                'exclude' => true,
                'sorting' => true,
                'inputType' => 'checkbox',
                'eval' => [
                    'mandatory' => false,
                    'tl_class' => 'clr w50',
                ],
                'attributes' => ['legend' => 'publish_legend'],
                'sql' => "char(1) NOT NULL default ''",
            ];
        }

        $dca['fields'] = array_merge(\is_array($dca['fields']) ? $dca['fields'] : [], $fields);

//        if (!$this->bundleConfig[$table]['exclude_from_palettes']) {
//        }
        $fieldManipulator = PaletteManipulator::create()
                ->addLegend('approval_legend', 'publish_legend', PaletteManipulator::POSITION_BEFORE)
                ->addField($fields, 'approval_legend', PaletteManipulator::POSITION_APPEND);

        foreach ($GLOBALS['TL_DCA'][$table]['palettes'] as $key => $palette) {
            if ('__selector__' === $key) {
                continue;
            }

            if (!$this->bundleConfig[$table]['exclude_from_palettes']) {
                $fieldManipulator->applyToPalette($key, $table);
            }
        }
    }
}
