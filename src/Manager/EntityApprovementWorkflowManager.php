<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovementBundle\Manager;

use Contao\Environment;
use Contao\Model;
use Contao\StringUtil;
use HeimrichHannot\EntityApprovementBundle\DataContainer\EntityApprovementContainer;
use HeimrichHannot\EntityApprovementBundle\DependencyInjection\Configuration;
use HeimrichHannot\EntityApprovementBundle\Dto\NotificationCenterOptionsDto;
use NotificationCenter\Model\Notification;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class EntityApprovementWorkflowManager
{
    const NOTIFICATION_TYPE_AUDITOR_CHANGED = 'huh_entity_approvement_auditor_changed';
    const NOTIFICATION_TYPE_STATE_CHANGED = 'huh_entity_approvement_state_changed';

    protected array               $bundleConfig;
    protected WorkflowInterface   $entityApprovementStateMachine;
    protected TranslatorInterface $translator;

    public function __construct(TranslatorInterface $translator, WorkflowInterface $entityApprovementStateMachine, array $bundleConfig)
    {
        $this->bundleConfig = $bundleConfig;
        $this->entityApprovementStateMachine = $entityApprovementStateMachine;
        $this->translator = $translator;
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

        $this->sendMails($options);
    }

    public function sendMails(NotificationCenterOptionsDto $options): void
    {
        switch ($options->state) {
            case EntityApprovementContainer::APPROVEMENT_STATE_WAIT_FOR_INITIAL_AUDITOR:
                $initialAuditors = explode(',', $this->bundleConfig[$options->table]['initial_auditor_groups']);

                if (empty($initialAuditors)) {
                    break;
                }

                if ($this->bundleConfig[$options->table]['initial_auditor_mode'][Configuration::AUDITOR_MODE_RANDOM]) {
                    //send mails to all initial auditors
                    $options->recipients = array_merge($options->recipients, $initialAuditors);
                } elseif ($this->bundleConfig[$options->table]['initial_auditor_mode'][Configuration::AUDITOR_MODE_ALL]) {
                    //send mails to a random initial auditor
                    $options->recipients = array_merge($options->recipients, [$initialAuditors[array_rand($initialAuditors)]]);
                }

                break;

            case EntityApprovementContainer::APPROVEMENT_STATE_IN_PROGRESS:
                $stayedAuditors = array_intersect($options->auditorFormer, $options->auditorNew);

                if ($this->bundleConfig[$options->table]['emails']['auditor_changed_former'] && !empty($options->auditorFormer)) {
                    //send mails to former auditors he is not an auditor anymore
                    $options->recipients = array_merge($options->recipients, array_diff($options->auditorFormer, $stayedAuditors));
                }

                if ($this->bundleConfig[$options->table]['emails']['auditor_changed_new'] && !empty($options->auditorNew)) {
                    //send mails to new auditors who was not auditor before
                    $options->recipients = array_merge($options->recipients, array_diff($options->auditorNew, $stayedAuditors));
                }

                break;

            case EntityApprovementContainer::APPROVEMENT_STATE_APPROVED:
            case EntityApprovementContainer::APPROVEMENT_STATE_REJECTED:
                // send mail to author on final result of the entity approvement
                if ($this->bundleConfig[$options->table]['emails']['state_changed_author'] && !empty($options->author)) {
                    $options->recipients = array_merge($options->recipients, [$options->author]);
                }

                break;

            case EntityApprovementContainer::APPROVEMENT_STATE_CHANGES_REQUESTED:

                break;

            default:
                break;
        }

        $options->recipients = array_unique($options->recipients);

        $this->sendMail($options);
    }

    private function sendMail(NotificationCenterOptionsDto $options): void
    {
        $notificationCollection = Notification::findByType($options->type);

        if (null !== $notificationCollection) {
            $tokens = [];
            $tokens['approvement_recipients'] = $options->recipients;
            $tokens['approvement_entity_url'] = Environment::get('url').'contao?do=submission&table='.$options->table.'&id='.$options->entityId.'&act=edit';

            while ($notificationCollection->next()) {
                $notification = $notificationCollection->current();

                $notification->send($tokens);
            }
        }
    }
}
