<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovementBundle\Manager;

use Contao\Model;
use Contao\StringUtil;
use HeimrichHannot\EntityApprovementBundle\DataContainer\EntityApprovementContainer;
use HeimrichHannot\EntityApprovementBundle\DependencyInjection\Configuration;
use Symfony\Component\Workflow\WorkflowInterface;

class EntityApprovementWorkflowManager
{
    protected array             $bundleConfig;
    protected WorkflowInterface $entityApprovementStateMachine;

    public function __construct(WorkflowInterface $entityApprovementStateMachine, array $bundleConfig)
    {
        $this->bundleConfig = $bundleConfig;
        $this->entityApprovementStateMachine = $entityApprovementStateMachine;
    }

    public function startWorkflow(Model $model): void
    {
        if (null === $model->row()['huhApprovement_auditor']) {
//            $this->entityApprovementStateMachine->getEnabledTransitions($model);
//            $this->entityApprovementStateMachine->apply($model, EntityApprovementContainer::APPROVEMENT_STATE_WAIT_FOR_INITIAL_AUDITOR);
//            $model->huhApprovement_state = EntityApprovementContainer::APPROVEMENT_STATE_WAIT_FOR_INITIAL_AUDITOR;
            $model->save();
        }
    }

    public function workflowAuditorChange($value, Model $model): void
    {
        //if $value null and workflow is allowed -> state wait_for_initial_auditor
        if (null === $value && $this->entityApprovementStateMachine->can($model, EntityApprovementContainer::APPROVEMENT_TRANSITION_REMOVE_ALL_AUDITORS)) {
            $this->entityApprovementStateMachine->apply($model, EntityApprovementContainer::APPROVEMENT_TRANSITION_REMOVE_ALL_AUDITORS);
            $model->save();

            $this->sendMails(EntityApprovementContainer::APPROVEMENT_TRANSITION_REMOVE_ALL_AUDITORS, $model::getTable(), []);
        }

        // if value not null and workflow is allowed -> state in_progress
        if (null === $model->huhApprovement_auditors &&
             null !== $value &&
             $this->entityApprovementStateMachine->can($model, EntityApprovementContainer::APPROVEMENT_TRANSITION_ASSIGN_AUDITOR)
        ) {
            $this->entityApprovementStateMachine->apply($model, EntityApprovementContainer::APPROVEMENT_TRANSITION_ASSIGN_AUDITOR);
            $model->save();

            $this->sendMails(EntityApprovementContainer::APPROVEMENT_TRANSITION_ASSIGN_AUDITOR, $model::getTable(), []);
        }

        if (null !== $value &&
             $this->entityApprovementStateMachine->can($model, EntityApprovementContainer::APPROVEMENT_STATE_IN_PROGRESS)
        ) {
            $this->entityApprovementStateMachine->apply($model, EntityApprovementContainer::APPROVEMENT_STATE_IN_PROGRESS);
            $model->huhApprovement_state = EntityApprovementContainer::APPROVEMENT_STATE_IN_PROGRESS;
            $model->save();

            // this array contains already saved auditor groups("former") if existing, and also groups just assigned("new")
            $auditors = [
                'former_auditor' => StringUtil::deserialize($model->huhApprovement_auditor, true),
                'new_auditor' => StringUtil::deserialize($value, true),
                'author' => $model[$this->bundleConfig[$model::getTable()]['author_field']],
            ];

            $this->sendMails(EntityApprovementContainer::APPROVEMENT_STATE_IN_PROGRESS, $model::getTable(), $auditors);
        }
    }

    public function sendMails(string $state, string $table, array $involved): void
    {
        if ($this->bundleConfig[$table]['emails']['state_changed_author'] && isset($involved['author'])) {
            $this->sendMail($involved['author']);
        }

        switch ($state) {
            case EntityApprovementContainer::APPROVEMENT_STATE_WAIT_FOR_INITIAL_AUDITOR:
                $initialAuditors = explode(',', $this->bundleConfig[$table]['initial_auditor_groups']);

                if (empty($initialAuditors)) {
                    return;
                }

                if ($this->bundleConfig[$table]['initial_auditor_mode'][Configuration::AUDITOR_MODE_RANDOM]) {
                    //send mails to all initial auditors
                    $this->sendMail($initialAuditors);
                } elseif ($this->bundleConfig[$table]['initial_auditor_mode'][Configuration::AUDITOR_MODE_ALL]) {
                    //send mails to a random initial auditor
                    $this->sendMail([$initialAuditors[array_rand($initialAuditors)]]);
                }

                break;

            case EntityApprovementContainer::APPROVEMENT_STATE_IN_PROGRESS:
                $stayedAuditors = array_intersect($involved['former_auditor'], $involved['new_auditor']);

                if ($this->bundleConfig[$table]['emails']['auditor_changed_former'] && !empty($involved['former_auditor'])) {
                    //send mails to former auditors he is not an auditor anymore
                    $this->sendMail(array_diff($involved['former_auditor'], $stayedAuditors));
                }

                if ($this->bundleConfig[$table]['emails']['auditor_changed_new'] && !empty($involved['new_auditor'])) {
                    //send mails to new auditors who was not auditor before
                    $this->sendMail(array_diff($involved['new_auditor'], $stayedAuditors));
                }

                break;

            case EntityApprovementContainer::APPROVEMENT_STATE_APPROVED:

                break;

            case EntityApprovementContainer::APPROVEMENT_STATE_REJECTED:
                break;

            case EntityApprovementContainer::APPROVEMENT_STATE_CHANGES_REQUESTED:
                break;

            default:
                break;
        }
    }

    private function sendMail(array $recipients): void
    {
    }
}
