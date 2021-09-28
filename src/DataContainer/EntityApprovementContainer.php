<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovementBundle\DataContainer;

use Contao\DataContainer;
use Contao\StringUtil;
use HeimrichHannot\EntityApprovementBundle\Dto\NotificationCenterOptionsDto;
use HeimrichHannot\EntityApprovementBundle\Manager\EntityApprovementWorkflowManager;
use HeimrichHannot\EntityApprovementBundle\Manager\NotificationManager;
use HeimrichHannot\UtilsBundle\Database\DatabaseUtil;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;
use HeimrichHannot\UtilsBundle\User\UserUtil;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Workflow\Exception\TransitionException;
use Symfony\Component\Workflow\WorkflowInterface;

class EntityApprovementContainer
{
    const APPROVEMENT_STATE_WAIT_FOR_INITIAL_AUDITOR = 'wait_for_initial_auditor';
    const APPROVEMENT_STATE_IN_PROGRESS = 'in_progress';
    const APPROVEMENT_STATE_CHANGES_REQUESTED = 'changes_requested';
    const APPROVEMENT_STATE_APPROVED = 'approved';
    const APPROVEMENT_STATE_REJECTED = 'rejected';

    const APPROVEMENT_STATES = [
        self::APPROVEMENT_STATE_WAIT_FOR_INITIAL_AUDITOR,
        self::APPROVEMENT_STATE_IN_PROGRESS,
        self::APPROVEMENT_STATE_CHANGES_REQUESTED,
        self::APPROVEMENT_STATE_APPROVED,
        self::APPROVEMENT_STATE_REJECTED,
    ];

    const APPROVEMENT_ENTITY_STATES = [
        self::APPROVEMENT_STATE_IN_PROGRESS,
        self::APPROVEMENT_STATE_CHANGES_REQUESTED,
        self::APPROVEMENT_STATE_APPROVED,
        self::APPROVEMENT_STATE_REJECTED,
    ];

    const APPROVEMENT_TRANSITION_ASSIGN_AUDITOR = 'assign_auditor';
    const APPROVEMENT_TRANSITION_REMOVE_ALL_AUDITORS = 'remove_all_auditors';
    const APPROVEMENT_TRANSITION_APPROVE = 'approve';
    const APPROVEMENT_TRANSITION_REJECT = 'reject';
    const APPROVEMENT_TRANSITION_REQUEST_CHANGE = 'request_change';
    const APPROVEMENT_TRANSITION_APPLY_CHANGE = 'apply_change';

    const APPROVEMENT_TRANSITIONS = [
        self::APPROVEMENT_TRANSITION_ASSIGN_AUDITOR,
        self::APPROVEMENT_TRANSITION_REMOVE_ALL_AUDITORS,
        self::APPROVEMENT_TRANSITION_APPROVE,
        self::APPROVEMENT_TRANSITION_REJECT,
        self::APPROVEMENT_TRANSITION_REQUEST_CHANGE,
        self::APPROVEMENT_TRANSITION_APPLY_CHANGE,
    ];

    protected DatabaseUtil                     $databaseUtil;
    protected EntityApprovementWorkflowManager $workflowManager;
    protected ModelUtil                        $modelUtil;
    protected NotificationManager              $notificationManager;
    protected TranslatorInterface              $translator;
    protected UserUtil                         $userUtil;
    protected WorkflowInterface                $entityApprovementStateMachine;
    protected array                            $bundleConfig;

    public function __construct(
        DatabaseUtil $databaseUtil,
        EntityApprovementWorkflowManager $workflowManager,
        ModelUtil $modelUtil,
        NotificationManager $notificationManager,
        TranslatorInterface $translator,
        UserUtil $userUtil,
        WorkflowInterface $entityApprovementStateMachine,
        array $bundleConfig
    ) {
        $this->databaseUtil = $databaseUtil;
        $this->workflowManager = $workflowManager;
        $this->modelUtil = $modelUtil;
        $this->notificationManager = $notificationManager;
        $this->translator = $translator;
        $this->userUtil = $userUtil;
        $this->entityApprovementStateMachine = $entityApprovementStateMachine;
        $this->bundleConfig = $bundleConfig;
    }

    public function getAuditors(?DataContainer $dc): array
    {
        $options = [];
        $entityConfig = $this->getEntityConfig($dc);

        $groups = explode(',', $this->bundleConfig[$dc->table]['auditor_groups']);

        $activeGroups = $this->databaseUtil->findResultsBy('tl_user_group', ['tl_user_group.disable!=?'], ['1'])->fetchAllAssoc();

        foreach ($activeGroups as $group) {
            if (\in_array($group['id'], $groups)) {
                $options[$group['id']] = $group['name'];
            }
        }

        return $options;
    }

    public function onAuditorsSave($value, DataContainer $dc)
    {
        $model = $this->modelUtil->findModelInstanceByPk($dc->table, $dc->id);
        $this->workflowManager->workflowAuditorChange($value, $model);

        return $value;
    }

    public function onStateSave($value, DataContainer $dc)
    {
        $model = $this->modelUtil->findModelInstanceByPk($dc->table, $dc->id);
        $activeRecord = $dc->activeRecord->row();

        $options = new NotificationCenterOptionsDto();
        $options->table = $dc->table;
        $options->entityId = $dc->id;
        $options->author = $model->__get($this->bundleConfig[$model::getTable()]['author_field']);
        $options->recipients = StringUtil::deserialize($activeRecord['huhApprovement_auditor'], true);
        $options->state = $value;
        $options->type = EntityApprovementWorkflowManager::NOTIFICATION_TYPE_STATE_CHANGED;

        if ($value === $activeRecord['huhApprovement_state'] || $this->userUtil->isAdmin()) {
            $this->notificationManager->sendNotifications($options);

            return $value;
        }

        $currentState = $activeRecord['huhApprovement_state'];
        $transitionName = $this->workflowManager->getTransitionName($currentState, $value);

        if (empty($transitionName)) {
            $message = sprintf(
                $this->translator->trans('huh.entity_approvement.bocking.transition_not_allowed'),
                $GLOBALS['TL_LANG']['MSC']['approvement_state'][$currentState],
                $GLOBALS['TL_LANG']['MSC']['approvement_state'][$value]
            );

            throw new TransitionException($model, $transitionName, $this->entityApprovementStateMachine, $message);
        }

        if ($currentState !== static::APPROVEMENT_STATE_APPROVED && $activeRecord[$this->bundleConfig[$dc->table]['publish_field']] === ($this->bundleConfig[$dc->table]['invert_publish_field'] ? '0' : '1')) {
            $value = $this->bundleConfig[$dc->table]['invert_publish_field'] ? '1' : '0';
            $this->databaseUtil->update(
                $dc->table,
                [$dc->table.'.'.$this->bundleConfig[$dc->table]['publish_field'].'='.$value],
                $dc->table.'.id='.$activeRecord['id']);
        }

        $this->notificationManager->sendNotifications($options);

        return $value;
    }

    public function onPublish($value, DataContainer $dc)
    {
        //Admin still can publish event without workflow
        if ($this->userUtil->isAdmin() || ($this->bundleConfig[$dc->table]['invert_publish_field'] && '1' === $value) || (!$this->bundleConfig[$dc->table]['invert_publish_field'] && '0' === $value)) {
            return $value;
        }

        $activeRecord = $dc->activeRecord->row();

        $state = $activeRecord['huhApprovement_state'];

        if ($value === ($this->bundleConfig[$dc->table]['invert_publish_field'] ? '0' : '1') && $state !== static::APPROVEMENT_STATE_APPROVED) {
            $unpublishValue = $this->bundleConfig[$dc->table]['invert_publish_field'] ? '1' : '0';

            $this->databaseUtil->update(
                $dc->table,
                [$dc->table.'.'.$this->bundleConfig[$dc->table]['publish_field'] => $unpublishValue],
                $dc->table.'.id=?',
                [$dc->id]);

            $message = sprintf(
                $this->translator->trans('huh.entity_approvement.bocking.publishing_blocked'),
                $GLOBALS['TL_LANG']['MSC']['approvement_state'][static::APPROVEMENT_STATE_APPROVED],
                $GLOBALS['TL_LANG']['MSC']['approvement_state'][$state]
            );

            throw new \Exception($message);
        }

        return $value;
    }

    //get auditorGroups from tl_page configuration
    //if not configured -> parent page -> till root page reached
    //still not configured get it from yaml config
    // TODO: can this be generalized on page level?
    private function getEntityConfig(DataContainer $dc): array
    {
        $config = [];

        $id = $dc->id;
        $table = $dc->table;

        $entity = $this->databaseUtil->findResultByPk($table, $id)->fetchAssoc();

        return $config;
    }
}
