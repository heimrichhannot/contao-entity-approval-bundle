<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovalBundle\Manager;

use Contao\BackendUser;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\Input;
use Contao\StringUtil;
use HeimrichHannot\EntityApprovalBundle\DataContainer\EntityApprovalContainer;
use HeimrichHannot\UtilsBundle\Dca\DcaUtil;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;
use HeimrichHannot\UtilsBundle\User\UserUtil;
use HeimrichHannot\UtilsBundle\Util\Container\ContainerUtil;
use function Symfony\Component\String\b;
use Symfony\Contracts\Translation\TranslatorInterface;

class DcaManager
{
    protected array               $bundleConfig;
    protected DcaUtil             $dcaUtil;
    protected UserUtil            $userUtil;
    protected ModelUtil           $modelUtil;
    protected ContainerUtil       $containerUtil;
    protected TranslatorInterface $translator;

    public function __construct(
        ContainerUtil $containerUtil,
        DcaUtil $dcaUtil,
        UserUtil $userUtil,
        ModelUtil $modelUtil,
        TranslatorInterface $translator,
        array $bundleConfig)
    {
        $this->bundleConfig = $bundleConfig;
        $this->dcaUtil = $dcaUtil;
        $this->userUtil = $userUtil;
        $this->modelUtil = $modelUtil;
        $this->containerUtil = $containerUtil;
        $this->translator = $translator;
    }

    public function addApprovalToDca(string $table): void
    {
        $this->dcaUtil->loadDc($table);
        $this->addApprovalFieldsToDca($table);

        if (null === ($entity = $this->modelUtil->findModelInstanceByPk($table, Input::get('id')))) {
            return;
        }

        $author = $entity->row()[$this->bundleConfig[$table]['author_email_field']];

        $dca = &$GLOBALS['TL_DCA'][$table];

        $backendUser = BackendUser::getInstance();

        if (((string) $backendUser->email === (string) $author || \in_array((string) $backendUser->id, StringUtil::deserialize($entity->huh_approval_auditor, true))) &&
            EntityApprovalContainer::APPROVAL_STATE_IN_AUDIT !== $entity->huh_approval_state
        ) {
            unset($dca['fields']['huh_approval_auditor'],
                $dca['fields']['huh_approval_transition'],
                $dca['fields']['huh_approval_inform_author'],
                $dca['fields']['huh_approval_confirm_continue'],
                $dca['fields']['huh_approval_notes']
            );

            foreach ($this->bundleConfig[$table]['auditor_levels'] as $level) {
                unset($dca['fields']['huh_approval_state_'.b($level['name'])->lower()]);
            }
        }

        $dca['config']['oncreate_callback'][] = [EntityApprovalContainer::class, 'onCreate'];
        $dca['config']['onsubmit_callback'][] = [EntityApprovalContainer::class, 'onSubmit'];
        $dca['config']['ctable'][] = 'tl_entity_approval_history';
        $dca['fields'][$this->bundleConfig[$table]['publish_field']]['save_callback'] = [[EntityApprovalContainer::class, 'onPublish']];

        $dca['list']['operations']['show_history'] = [
            'label' => &$GLOBALS['TL_LANG']['MSG']['approval_history'],
            'href' => 'table=tl_entity_approval_history&ptable='.$table,
            'icon' => 'diff.gif',
        ];
    }

    public function addAuthorFieldsToHistory()
    {
        $this->dcaUtil->addAuthorFieldAndCallback('tl_entity_approval_history');
        $ptable = Input::get('ptable');

        if ($ptable && !\array_key_exists($ptable, $this->bundleConfig)) {
            $message = sprintf(
                $this->translator->trans('huh.entity_approval.data_container.ptable_not_allowed'),
                [$ptable, 'tl_entity_approval_history'],
            );

            throw new \Exception($message);
        }

        if ($this->containerUtil->isBackend()) {
            $dca = &$GLOBALS['TL_DCA']['tl_entity_approval_history'];
            $dca['config']['ptable'] = $ptable;
            $dca['fields']['author']['eval']['readonly'] = true;
            $dca['fields']['author']['eval']['tl_class'] = 'w50 readonly';
            $dca['fields']['authorType']['sql'] = "varchar(255) NOT NULL default 'user'";
        }
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
                'options_callback' => [EntityApprovalContainer::class, 'onAuditorOptionsCallback'],
                'eval' => ['multiple' => false, 'mandatory' => false, 'tl_class' => 'clr w50', 'includeBlankOption' => true],
                'attributes' => ['legend' => 'publish_legend', 'fe_sorting' => true, 'fe_search' => true],
                'sql' => 'blob NULL',
            ],
            'huh_approval_transition' => [
                'label' => &$GLOBALS['TL_LANG']['MSC']['approval_transition'],
                'filter' => true,
                'inputType' => 'select',
                'options_callback' => [EntityApprovalContainer::class, 'getAvailableTransitions'],
                'load_callback' => [EntityApprovalContainer::class, 'onLoadApprovalTransition'],
                'eval' => ['tl_class' => 'w50', 'includeBlankOption' => true],
                'sql' => "varchar(64) NOT NULL default ''",
            ],
            'huh_approval_inform_author' => [
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
            ],
            'huh_approval_confirm_continue' => [
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
            ],
            'huh_approval_notes' => [
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
        ];

        foreach ($this->bundleConfig[$table]['auditor_levels'] as $level) {
            if (null === ($userGroups = $this->userUtil->getActiveGroups($userModel->id)) && !$this->userUtil->isAdmin($userModel->id)) {
                continue;
            }

            $fields['huh_approval_state_'.b($level['name'])->lower()] = [
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
            ];
        }

        $dca['fields'] = array_merge(\is_array($dca['fields']) ? $dca['fields'] : [], $fields);

        $fieldManipulator = PaletteManipulator::create()
                ->addLegend('approval_legend', 'publish_legend', PaletteManipulator::POSITION_BEFORE)
                ->addField(array_keys($fields), 'approval_legend', PaletteManipulator::POSITION_APPEND);

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
