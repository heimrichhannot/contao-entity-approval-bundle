<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\EntityApprovalBundle\EventSubscriber;

use Contao\Model;
use Contao\StringUtil;
use HeimrichHannot\EntityApprovalBundle\DataContainer\EntityApprovalContainer;
use HeimrichHannot\EntityApprovalBundle\DependencyInjection\Configuration;
use HeimrichHannot\EntityApprovalBundle\Dto\NotificationCenterOptionsDto;
use HeimrichHannot\EntityApprovalBundle\Manager\NotificationManager;
use HeimrichHannot\UtilsBundle\User\UserUtil;
use Model\Collection;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\EnteredEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class WorkflowEventSubscriber implements EventSubscriberInterface
{
    const ENTITY_APPROVAL_WORKFLOW_NAME = 'entity_approval';

    protected TranslatorInterface $translator;
    protected LoggerInterface     $logger;
    protected WorkflowInterface   $entityApprovalStateMachine;
    protected array               $bundleConfig;
    protected UserUtil            $userUtil;
    protected NotificationManager $notificationManager;

    public function __construct(
        LoggerInterface $logger,
        NotificationManager $notificationManager,
        TranslatorInterface $translator,
        UserUtil $userUtil,
        WorkflowInterface $entityApprovalStateMachine,
        array $bundleConfig
    ) {
        $this->translator = $translator;
        $this->logger = $logger;
        $this->entityApprovalStateMachine = $entityApprovalStateMachine;
        $this->bundleConfig = $bundleConfig;
        $this->userUtil = $userUtil;
        $this->notificationManager = $notificationManager;
    }

    public static function getSubscribedEvents()
    {
        return [
            'workflow.entity_approval.entered.wait_for_initial_auditor' => ['onWorkflowEnteredInitialPlace'],
            'workflow.entity_approval.transition.assign_auditor' => ['onWorkflowTransitionAssignAuditor'],
            'workflow.entity_approval.transition.request_change' => ['onWorkflowTransitionRequestChange'],
            'workflow.entity_approval.transition.apply_change' => ['onWorkflowTransitionApplyChange'],
            'workflow.entity_approval.transition.approve' => ['onWorkflowTransitionApprove'],
            'workflow.entity_approval.transition.reject' => ['onWorkflowTransitionReject'],
        ];
    }

    public function onWorkflowEnteredInitialPlace(EnteredEvent $event)
    {
        /** @var Model $model */
        $model = $event->getSubject();
        $table = $model->getTable();

        $groups = explode(',', $this->bundleConfig[$table]['initial_auditor_groups']);

        /** @var Collection $users */
        if (null === ($users = $this->userUtil->findActiveByGroups($groups))) {
            return;
        }

        $auditors = array_column($users->fetchAll(), 'email');

        //select initial auditor and send notification
        $mode = $this->bundleConfig[$table]['initial_auditor_mode'];
        $options = new NotificationCenterOptionsDto();

        if (Configuration::AUDITOR_MODE_RANDOM === $mode) {
            $options->recipients = $auditors[array_rand($auditors)];
        } else {
            $options->recipients = implode(',', $auditors);
        }

        $options->type = NotificationManager::NOTIFICATION_TYPE_STATE_CHANGED;
        $options->state = array_search(1, $event->getMarking()->getPlaces(), true);
        $options->transition = $event->getTransition()->getName();
        $options->table = $table;
        $options->entityId = $model->id;
        $options->author = $model->__get($this->bundleConfig[$table]['author_field']);

        if (Configuration::AUDITOR_MODE_RANDOM === $mode) {
            $options->recipients = $auditors[array_rand($auditors)];
        } else {
            $options->recipients = implode(',', $auditors);
        }

        $this->notificationManager->sendMail($options);
    }

    public function onWorkflowTransitionAssignAuditor(TransitionEvent $event)
    {
        /** @var Model $model */
        $model = $event->getSubject();
        $table = $model->getTable();

        $options = new NotificationCenterOptionsDto();
        $options->table = $table;
        $options->author = $model->__get($this->bundleConfig[$table]['author_field']);
        $options->auditor = StringUtil::deserialize($model->huhApproval_auditor, true);
        $options->state = EntityApprovalContainer::APPROVAL_STATE_WAIT_FOR_INITIAL_AUDITOR;
        $options->type = NotificationManager::NOTIFICATION_TYPE_AUDITOR_CHANGED;

        $recipients = $this->userUtil->findActiveByGroups(StringUtil::deserialize($model->huhApproval_auditor, true));
        $options->recipients = implode(',', array_column($recipients, 'email'));

        $this->notificationManager->sendMail($options);
    }

    public function onWorkflowTransitionRequestChange(TransitionEvent $event)
    {
        /** @var Model $model */
        $model = $event->getSubject();
        $table = $model->getTable();

        $options = new NotificationCenterOptionsDto();
        $options->table = $table;
        $options->author = $model->__get($this->bundleConfig[$table]['author_field']);
        $options->auditor = StringUtil::deserialize($model->huhApproval_auditor, true);
        $options->state = EntityApprovalContainer::APPROVAL_STATE_CHANGES_REQUESTED;
        $options->type = NotificationManager::NOTIFICATION_TYPE_STATE_CHANGED;
        $options->entityId = $model->id;
        $options->transition = EntityApprovalContainer::APPROVAL_TRANSITION_REQUEST_CHANGE;

        if ((bool) $model->huhApproval_informAuthor) {
            $options->recipients = $model->__get($this->bundleConfig[$table]['author_field']);
            $this->notificationManager->sendMail($options);
        }
    }

    public function onWorkflowTransitionApplyChange(TransitionEvent $event)
    {
        /** @var Model $model */
        $model = $event->getSubject();
        $table = $model->getTable();

        $options = new NotificationCenterOptionsDto();
        $options->table = $table;
        $options->author = $model->__get($this->bundleConfig[$table]['author_field']);
        $options->auditor = StringUtil::deserialize($model->huhApproval_auditor, true);
        $options->state = EntityApprovalContainer::APPROVAL_STATE_IN_PROGRESS;
        $options->type = NotificationManager::NOTIFICATION_TYPE_STATE_CHANGED;
        $options->entityId = $model->id;
        $options->transition = EntityApprovalContainer::APPROVAL_TRANSITION_APPLY_CHANGE;

        if ((bool) $model->huhApproval_informAuthor) {
            $options->recipients = $model->__get($this->bundleConfig[$table]['author_field']);
            $this->notificationManager->sendMail($options);
        }
    }

    public function onWorkflowTransitionApprove(TransitionEvent $event)
    {
        /** @var Model $model */
        $model = $event->getSubject();
        $table = $model->getTable();

        $options = new NotificationCenterOptionsDto();
        $options->table = $table;
        $options->author = $model->__get($this->bundleConfig[$table]['author_field']);
        $options->auditor = StringUtil::deserialize($model->huhApproval_auditor, true);
        $options->state = EntityApprovalContainer::APPROVAL_STATE_APPROVED;
        $options->type = NotificationManager::NOTIFICATION_TYPE_STATE_CHANGED;
        $options->entityId = $model->id;
        $options->transition = EntityApprovalContainer::APPROVAL_TRANSITION_APPROVE;

        if ((bool) $model->huhApproval_informAuthor) {
            $options->recipients = $model->__get($this->bundleConfig[$table]['author_field']);
            $this->notificationManager->sendMail($options);
        }
    }

    public function onWorkflowTransitionReject(TransitionEvent $event)
    {
        /** @var Model $model */
        $model = $event->getSubject();
        $table = $model->getTable();

        $options = new NotificationCenterOptionsDto();
        $options->table = $table;
        $options->author = $model->__get($this->bundleConfig[$table]['author_field']);
        $options->auditor = StringUtil::deserialize($model->huhApproval_auditor, true);
        $options->state = EntityApprovalContainer::APPROVAL_STATE_REJECTED;
        $options->type = NotificationManager::NOTIFICATION_TYPE_STATE_CHANGED;
        $options->entityId = $model->id;
        $options->transition = EntityApprovalContainer::APPROVAL_TRANSITION_REJECT;

        if ((bool) $model->huhApproval_informAuthor) {
            $options->recipients = $model->__get($this->bundleConfig[$table]['author_field']);
            $this->notificationManager->sendMail($options);
        }
    }
}
