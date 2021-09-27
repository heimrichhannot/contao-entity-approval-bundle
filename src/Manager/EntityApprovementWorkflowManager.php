<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovementBundle\Manager;

use Contao\Model;
use Contao\StringUtil;
use HeimrichHannot\EntityApprovementBundle\DataContainer\EntityApprovementContainer;
use HeimrichHannot\EntityApprovementBundle\Dto\NotificationCenterOptionsDto;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class EntityApprovementWorkflowManager
{
    const NOTIFICATION_TYPE_AUDITOR_CHANGED = 'huh_entity_approvement_auditor_changed';
    const NOTIFICATION_TYPE_STATE_CHANGED = 'huh_entity_approvement_state_changed';

    protected array               $bundleConfig;
    protected WorkflowInterface   $entityApprovementStateMachine;
    protected TranslatorInterface $translator;
    protected NotificationManager $notificationManager;

    public function __construct(TranslatorInterface $translator, WorkflowInterface $entityApprovementStateMachine, NotificationManager $notificationManager, array $bundleConfig)
    {
        $this->bundleConfig = $bundleConfig;
        $this->entityApprovementStateMachine = $entityApprovementStateMachine;
        $this->translator = $translator;
        $this->notificationManager = $notificationManager;
    }

    public function isTransitionPossible($value, Model $model): bool
    {
        $enabledTransitions = $this->entityApprovementStateMachine->getEnabledTransitions($model);

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

        foreach ($this->entityApprovementStateMachine->getDefinition()->getTransitions() as $transition) {
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
        $options->auditorFormer = StringUtil::deserialize($model->huhApprovement_auditor, true);
        $options->auditorNew = StringUtil::deserialize($value, true);
        $options->state = EntityApprovementContainer::APPROVEMENT_STATE_WAIT_FOR_INITIAL_AUDITOR;
        $options->type = static::NOTIFICATION_TYPE_AUDITOR_CHANGED;

        //if value null and transition is allowed -> state wait_for_initial_auditor
        if (null === $value && $this->entityApprovementStateMachine->can($model, EntityApprovementContainer::APPROVEMENT_TRANSITION_REMOVE_ALL_AUDITORS)) {
            $this->entityApprovementStateMachine->apply($model, EntityApprovementContainer::APPROVEMENT_TRANSITION_REMOVE_ALL_AUDITORS);
            $model->save();

            $options->state = EntityApprovementContainer::APPROVEMENT_TRANSITION_REMOVE_ALL_AUDITORS;
        }

        // if value not null and transition is allowed -> state in_progress
        if (null === $model->huhApprovement_auditors &&
             null !== $value &&
             $this->entityApprovementStateMachine->can($model, EntityApprovementContainer::APPROVEMENT_TRANSITION_ASSIGN_AUDITOR)
        ) {
            $this->entityApprovementStateMachine->apply($model, EntityApprovementContainer::APPROVEMENT_TRANSITION_ASSIGN_AUDITOR);
            $model->save();

            $options->state = EntityApprovementContainer::APPROVEMENT_TRANSITION_ASSIGN_AUDITOR;
        }

        if (null !== $value && $value !== $model->huhApprovement_auditor) {
            $options->state = EntityApprovementContainer::APPROVEMENT_STATE_IN_PROGRESS;
        }

        $this->notificationManager->sendNotifications($options);
    }
}
