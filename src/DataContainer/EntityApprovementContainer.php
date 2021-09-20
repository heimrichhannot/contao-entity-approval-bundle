<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovementBundle\DataContainer;

use Contao\DataContainer;
use HeimrichHannot\EntityApprovementBundle\Manager\EntityApprovementWorkflowManager;
use HeimrichHannot\UtilsBundle\Database\DatabaseUtil;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;
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

    protected array                            $bundleConfig;
    protected DatabaseUtil                     $databaseUtil;
    protected EntityApprovementWorkflowManager $workflowManager;
    protected ModelUtil                        $modelUtil;
    protected WorkflowInterface                $entityApprovementStateMachine;
    protected TranslatorInterface              $translator;

    public function __construct(
        DatabaseUtil $databaseUtil,
        ModelUtil $modelUtil,
        EntityApprovementWorkflowManager $workflowManager,
        array $bundleConfig,
        WorkflowInterface $entityApprovementStateMachine,
        TranslatorInterface $translator
    ) {
        $this->bundleConfig = $bundleConfig;
        $this->databaseUtil = $databaseUtil;
        $this->workflowManager = $workflowManager;
        $this->modelUtil = $modelUtil;
        $this->entityApprovementStateMachine = $entityApprovementStateMachine;
        $this->translator = $translator;
    }

    public function getAuditors(?DataContainer $dc): array
    {
        $options = [];

        $groups = explode(',', $this->bundleConfig[$dc->table]['auditor_groups']);

        $activeGroups = $this->databaseUtil->findResultsBy('tl_user_group', ['tl_user_group.disable!=?'], ['1'])->fetchAllAssoc();

        foreach ($activeGroups as $group) {
            if (\in_array($group['id'], $groups)) {
                $options[$group['id']] = $group['name'];
            }
        }

        return $options;
    }

    public function startWorkflow(string $table, int $insertId, array $fields, DataContainer $dc): void
    {
        $model = $this->modelUtil->findModelInstanceByPk($table, $insertId);
        $this->workflowManager->startWorkflow($model);
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
        $transitionName = '';

        foreach ($this->entityApprovementStateMachine->getDefinition()->getTransitions() as $transition) {
            if (\in_array($model->huhApprovement_state, $transition->getFroms()) && \in_array($value, $transition->getTos())) {
                $transitionName = $transition->getName();
            }
        }

        if (!empty($transitionName)) {
            if ($this->entityApprovementStateMachine->can($model, $transitionName)) {
                $this->entityApprovementStateMachine->apply($model, $transitionName);
            } else {
                throw new TransitionException($model, $transitionName, $this->entityApprovementStateMachine, 'test');
            }
        } else {
            $message = sprintf(
                $this->translator->trans('huh.entity_approvement.bocking.transition_not_allowed'),
                $GLOBALS['TL_LANG']['MSC']['approvement_state'][$model->huhApprovement_state],
                $GLOBALS['TL_LANG']['MSC']['approvement_state'][$value]
            );

            throw new \Exception($message);
        }

        return $value;
    }

    public function onNotesSave($value, DataContainer $dc)
    {
        $model = $this->modelUtil->findModelInstanceByPk($dc->table, $dc->id);
//        $this->workflowManager->workflowNotesChange($value, $model);

        return $value;
    }
}
