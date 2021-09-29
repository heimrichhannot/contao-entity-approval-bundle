<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovalBundle\DataContainer;

use Contao\DataContainer;
use Contao\StringUtil;
use HeimrichHannot\EntityApprovalBundle\Dto\NotificationCenterOptionsDto;
use HeimrichHannot\EntityApprovalBundle\Manager\EntityApprovalWorkflowManager;
use HeimrichHannot\EntityApprovalBundle\Manager\NotificationManager;
use HeimrichHannot\UtilsBundle\Database\DatabaseUtil;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;
use HeimrichHannot\UtilsBundle\User\UserUtil;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Workflow\Exception\TransitionException;
use Symfony\Component\Workflow\WorkflowInterface;

class EntityApprovalContainer
{
    const APPROVAL_STATE_CREATED = 'created';
    const APPROVAL_STATE_WAIT_FOR_INITIAL_AUDITOR = 'wait_for_initial_auditor';
    const APPROVAL_STATE_IN_PROGRESS = 'in_progress';
    const APPROVAL_STATE_CHANGES_REQUESTED = 'changes_requested';
    const APPROVAL_STATE_APPROVED = 'approved';
    const APPROVAL_STATE_REJECTED = 'rejected';

    const APPROVAL_STATES = [
        self::APPROVAL_STATE_CREATED,
        self::APPROVAL_STATE_WAIT_FOR_INITIAL_AUDITOR,
        self::APPROVAL_STATE_IN_PROGRESS,
        self::APPROVAL_STATE_CHANGES_REQUESTED,
        self::APPROVAL_STATE_APPROVED,
        self::APPROVAL_STATE_REJECTED,
    ];

    const APPROVAL_ENTITY_STATES = [
        self::APPROVAL_STATE_IN_PROGRESS,
        self::APPROVAL_STATE_CHANGES_REQUESTED,
        self::APPROVAL_STATE_APPROVED,
        self::APPROVAL_STATE_REJECTED,
    ];

    const APPROVAL_TRANSITION_ASSIGN_INITIAL_AUDITOR = 'assign_initial_auditor';
    const APPROVAL_TRANSITION_ASSIGN_AUDITOR = 'assign_auditor';
    const APPROVAL_TRANSITION_REMOVE_ALL_AUDITORS = 'remove_all_auditors';
    const APPROVAL_TRANSITION_APPROVE = 'approve';
    const APPROVAL_TRANSITION_REJECT = 'reject';
    const APPROVAL_TRANSITION_REQUEST_CHANGE = 'request_change';
    const APPROVAL_TRANSITION_APPLY_CHANGE = 'apply_change';

    const APPROVAL_TRANSITIONS = [
        self::APPROVAL_TRANSITION_ASSIGN_INITIAL_AUDITOR,
        self::APPROVAL_TRANSITION_ASSIGN_AUDITOR,
        self::APPROVAL_TRANSITION_REMOVE_ALL_AUDITORS,
        self::APPROVAL_TRANSITION_APPROVE,
        self::APPROVAL_TRANSITION_REJECT,
        self::APPROVAL_TRANSITION_REQUEST_CHANGE,
        self::APPROVAL_TRANSITION_APPLY_CHANGE,
    ];

    protected DatabaseUtil                     $databaseUtil;
    protected EntityApprovalWorkflowManager $workflowManager;
    protected ModelUtil                        $modelUtil;
    protected NotificationManager              $notificationManager;
    protected TranslatorInterface              $translator;
    protected UserUtil                         $userUtil;
    protected WorkflowInterface                $entityApprovalStateMachine;
    protected array                            $bundleConfig;

    public function __construct(
        DatabaseUtil $databaseUtil,
        EntityApprovalWorkflowManager $workflowManager,
        ModelUtil $modelUtil,
        NotificationManager $notificationManager,
        TranslatorInterface $translator,
        UserUtil $userUtil,
        WorkflowInterface $entityApprovalStateMachine,
        array $bundleConfig
    ) {
        $this->databaseUtil = $databaseUtil;
        $this->workflowManager = $workflowManager;
        $this->modelUtil = $modelUtil;
        $this->notificationManager = $notificationManager;
        $this->translator = $translator;
        $this->userUtil = $userUtil;
        $this->entityApprovalStateMachine = $entityApprovalStateMachine;
        $this->bundleConfig = $bundleConfig;
    }

    public function onSubmit(DataContainer $dc): void
    {

        $model = $this->modelUtil->findModelInstanceByPk($dc->table, $dc->id);

        $marking = $this->entityApprovalStateMachine->getMarking($model);
        $test = '';
    }

    public function onCreate(string $table, int $id, array $fields, DataContainer $dc): void
    {
        $model = $this->modelUtil->findModelInstanceByPk($table, $id);
        $this->entityApprovalStateMachine->apply($model, static::APPROVAL_TRANSITION_ASSIGN_INITIAL_AUDITOR);
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
        $options->recipients = StringUtil::deserialize($activeRecord['huhApproval_auditor'], true);
        $options->state = $value;
        $options->type = EntityApprovalWorkflowManager::NOTIFICATION_TYPE_STATE_CHANGED;

        if ($value === $activeRecord['huhApproval_state'] || $this->userUtil->isAdmin()) {
            $this->notificationManager->sendNotifications($options);

            return $value;
        }

        $currentState = $activeRecord['huhApproval_state'];
        $transitionName = $this->workflowManager->getTransitionName($currentState, $value);

        if (empty($transitionName)) {
            $message = sprintf(
                $this->translator->trans('huh.entity_approval.bocking.transition_not_allowed'),
                $GLOBALS['TL_LANG']['MSC']['approval_state'][$currentState],
                $GLOBALS['TL_LANG']['MSC']['approval_state'][$value]
            );

            throw new TransitionException($model, $transitionName, $this->entityApprovalStateMachine, $message);
        }

        if ($currentState !== static::APPROVAL_STATE_APPROVED && $activeRecord[$this->bundleConfig[$dc->table]['publish_field']] === ($this->bundleConfig[$dc->table]['invert_publish_field'] ? '0' : '1')) {
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

        $state = $activeRecord['huhApproval_state'];

        if ($value === ($this->bundleConfig[$dc->table]['invert_publish_field'] ? '0' : '1') && $state !== static::APPROVAL_STATE_APPROVED) {
            $unpublishValue = $this->bundleConfig[$dc->table]['invert_publish_field'] ? '1' : '0';

            $this->databaseUtil->update(
                $dc->table,
                [$dc->table.'.'.$this->bundleConfig[$dc->table]['publish_field'] => $unpublishValue],
                $dc->table.'.id=?',
                [$dc->id]);

            $message = sprintf(
                $this->translator->trans('huh.entity_approval.bocking.publishing_blocked'),
                $GLOBALS['TL_LANG']['MSC']['approval_state'][static::APPROVAL_STATE_APPROVED],
                $GLOBALS['TL_LANG']['MSC']['approval_state'][$state]
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
