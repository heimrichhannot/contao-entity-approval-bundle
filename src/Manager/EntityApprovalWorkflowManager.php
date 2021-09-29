<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovalBundle\Manager;

use Contao\Model;
use Contao\StringUtil;
use HeimrichHannot\EntityApprovalBundle\DataContainer\EntityApprovalContainer;
use HeimrichHannot\EntityApprovalBundle\Dto\NotificationCenterOptionsDto;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class EntityApprovalWorkflowManager
{
    const NOTIFICATION_TYPE_AUDITOR_CHANGED = 'huh_entity_approval_auditor_changed';
    const NOTIFICATION_TYPE_STATE_CHANGED = 'huh_entity_approval_state_changed';

    protected array               $bundleConfig;
    protected WorkflowInterface   $entityApprovalStateMachine;
    protected TranslatorInterface $translator;
    protected NotificationManager $notificationManager;

    public function __construct(TranslatorInterface $translator, WorkflowInterface $entityApprovalStateMachine, NotificationManager $notificationManager, array $bundleConfig)
    {
        $this->bundleConfig = $bundleConfig;
        $this->entityApprovalStateMachine = $entityApprovalStateMachine;
        $this->translator = $translator;
        $this->notificationManager = $notificationManager;
    }

    public function isTransitionPossible($value, Model $model): bool
    {
        $enabledTransitions = $this->entityApprovalStateMachine->getEnabledTransitions($model);

        foreach ($enabledTransitions as $transition) {
            if (\in_array($value, $transition->getTos())) {
                return true;
            }
        }

        return false;
    }

    public function getTransitionName(string $from, string $to): string
    {
        $name = '';

        foreach ($this->entityApprovalStateMachine->getDefinition()->getTransitions() as $transition) {
            if (\in_array($from, $transition->getFroms()) && \in_array($to, $transition->getTos())) {
                $name = $transition->getName();
            }
        }

        return $name;
    }

    public function workflowAuditorChange($value, Model $model): void
    {
        $options = new NotificationCenterOptionsDto();
        $options->table = $model::getTable();
        $options->author = $model->__get($this->bundleConfig[$model::getTable()]['author_field']);
        $options->auditorFormer = StringUtil::deserialize($model->huhApproval_auditor, true);
        $options->auditorNew = StringUtil::deserialize($value, true);
        $options->state = EntityApprovalContainer::APPROVAL_STATE_WAIT_FOR_INITIAL_AUDITOR;
        $options->type = static::NOTIFICATION_TYPE_AUDITOR_CHANGED;

        //if value null and transition is allowed -> state wait_for_initial_auditor
        if (null === $value && $this->entityApprovalStateMachine->can($model, EntityApprovalContainer::APPROVAL_TRANSITION_REMOVE_ALL_AUDITORS)) {
            $this->entityApprovalStateMachine->apply($model, EntityApprovalContainer::APPROVAL_TRANSITION_REMOVE_ALL_AUDITORS);
            $model->save();

            $options->state = EntityApprovalContainer::APPROVAL_TRANSITION_REMOVE_ALL_AUDITORS;
        }

        // if value not null and transition is allowed -> state in_progress
        if (null === $model->huhApproval_auditors &&
             null !== $value &&
             $this->entityApprovalStateMachine->can($model, EntityApprovalContainer::APPROVAL_TRANSITION_ASSIGN_AUDITOR)
        ) {
            $this->entityApprovalStateMachine->apply($model, EntityApprovalContainer::APPROVAL_TRANSITION_ASSIGN_AUDITOR);
            $model->save();

            $options->state = EntityApprovalContainer::APPROVAL_TRANSITION_ASSIGN_AUDITOR;
        }

        if (null !== $value && $value !== $model->huhApproval_auditor) {
            $options->state = EntityApprovalContainer::APPROVAL_STATE_IN_PROGRESS;
        }

        $this->notificationManager->sendNotifications($options);
    }
}
